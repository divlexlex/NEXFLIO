<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payments header: one Status dropdown (pending/verified/rejected/all/
 * refund/no_refund) + Amounts select, 6 cards per page on both pagination
 * paths, and equal-height cards with bottom-pinned action blocks.
 */
class PaymentStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;

    private User $client;

    private User $staff;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->service = Service::create([
            'name' => 'Filter Facial (test)', 'category' => 'Facial', 'description' => 'Test',
            'price' => 800, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
    }

    private function makeAppointment(AppointmentStatus $status, float $bookedPrice = 800): Appointment
    {
        return Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'status' => $status,
            'booked_price' => $bookedPrice,
        ]);
    }

    private function makeVerifiedPayment(Appointment $appointment, float $amount, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'appointment_id' => $appointment->id,
            'amount' => $amount,
            'method' => 'gcash',
            'status' => PaymentStatus::Verified,
            'verified_by' => $this->manager->id,
            'verified_at' => now(),
        ], $overrides));
    }

    private function approveCancellation(Appointment $appointment): void
    {
        AppointmentChangeRequest::create([
            'appointment_id' => $appointment->id,
            'requested_by' => $this->client->id,
            'type' => AppointmentChangeRequest::TYPE_CANCELLATION,
            'status' => AppointmentChangeRequest::STATUS_APPROVED,
            'reviewed_by' => $this->manager->id,
        ]);
    }

    private function pageHtml(array $params = []): string
    {
        return $this->actingAs($this->manager)
            ->get(route('admin.payments', $params))
            ->assertOk()
            ->getContent();
    }

    public function test_refund_filter_shows_only_refundable_cancelled(): void
    {
        $refundable = $this->makeAppointment(AppointmentStatus::Cancelled);
        $this->makeVerifiedPayment($refundable, 300, ['refund_status' => 'requested']);
        $this->approveCancellation($refundable);

        $plain = $this->makeAppointment(AppointmentStatus::Cancelled);
        $this->makeVerifiedPayment($plain, 300);
        $this->approveCancellation($plain);

        $unrelated = $this->makeAppointment(AppointmentStatus::Cancelled);
        $this->makeVerifiedPayment($unrelated, 300);

        $html = $this->pageHtml(['status' => 'refund']);

        $this->assertStringContainsString($refundable->referenceCode(), $html);
        $this->assertStringNotContainsString($plain->referenceCode(), $html);
        $this->assertStringNotContainsString($unrelated->referenceCode(), $html);
    }

    public function test_no_refund_filter_shows_only_non_refundable_cancelled(): void
    {
        $refundable = $this->makeAppointment(AppointmentStatus::Cancelled);
        $this->makeVerifiedPayment($refundable, 300, ['refund_status' => 'requested']);
        $this->approveCancellation($refundable);

        $plain = $this->makeAppointment(AppointmentStatus::Cancelled);
        $this->makeVerifiedPayment($plain, 300);
        $this->approveCancellation($plain);

        $unrelated = $this->makeAppointment(AppointmentStatus::Cancelled);
        $this->makeVerifiedPayment($unrelated, 300);

        $html = $this->pageHtml(['status' => 'no_refund']);

        $this->assertStringContainsString($plain->referenceCode(), $html);
        $this->assertStringNotContainsString($refundable->referenceCode(), $html);
        $this->assertStringNotContainsString($unrelated->referenceCode(), $html);
    }

    public function test_existing_statuses_work_and_combine_with_balance(): void
    {
        $pending = $this->makeAppointment(AppointmentStatus::Unverified);
        Payment::create([
            'appointment_id' => $pending->id,
            'amount' => 300,
            'method' => 'gcash',
            'proof_path' => 'payment_proofs/test.jpg',
            'status' => PaymentStatus::Pending,
        ]);

        $due = $this->makeAppointment(AppointmentStatus::Booked);
        $this->makeVerifiedPayment($due, 300);

        $settled = $this->makeAppointment(AppointmentStatus::Booked);
        $this->makeVerifiedPayment($settled, 800);

        $default = $this->pageHtml();
        $this->assertStringContainsString($pending->referenceCode(), $default);
        $this->assertStringNotContainsString($due->referenceCode(), $default);

        $verified = $this->pageHtml(['status' => 'verified']);
        $this->assertStringContainsString($due->referenceCode(), $verified);
        $this->assertStringContainsString($settled->referenceCode(), $verified);
        $this->assertStringNotContainsString($pending->referenceCode(), $verified);

        $all = $this->pageHtml(['status' => 'all']);
        $this->assertStringContainsString($pending->referenceCode(), $all);
        $this->assertStringContainsString($due->referenceCode(), $all);

        $dueOnly = $this->pageHtml(['status' => 'verified', 'balance' => 'due']);
        $this->assertStringContainsString($due->referenceCode(), $dueOnly);
        $this->assertStringNotContainsString($settled->referenceCode(), $dueOnly);
    }

    public function test_pagination_renders_six_per_page_on_both_paths(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $appointment = $this->makeAppointment(AppointmentStatus::Booked);
            $appointment->update([
                'start_time' => sprintf('%02d:00', 9 + $i),
                'reference_code' => sprintf('PN-PAGE-%06d', $i),
            ]);
            $payment = $this->makeVerifiedPayment($appointment, 300);
            // Stagger payment timestamps so last_payment_at ordering is
            // deterministic: index 0 newest.
            $payment->update(['created_at' => now()->subSeconds($i), 'updated_at' => now()->subSeconds($i)]);
        }

        $page1 = $this->pageHtml(['status' => 'all']);
        $this->assertSame(6, substr_count($page1, 'col-md-6 col-xl-4'));
        $this->assertStringContainsString('PN-PAGE-000005', $page1);
        $this->assertStringNotContainsString('PN-PAGE-000006', $page1);

        $page2 = $this->pageHtml(['status' => 'all', 'page' => 2]);
        $this->assertSame(1, substr_count($page2, 'col-md-6 col-xl-4'));
        $this->assertStringContainsString('PN-PAGE-000006', $page2);

        $filtered1 = $this->pageHtml(['status' => 'all', 'balance' => 'due']);
        $this->assertSame(6, substr_count($filtered1, 'col-md-6 col-xl-4'));

        $filtered2 = $this->pageHtml(['status' => 'all', 'balance' => 'due', 'page' => 2]);
        $this->assertSame(1, substr_count($filtered2, 'col-md-6 col-xl-4'));
    }

    public function test_cards_carry_equal_height_classes(): void
    {
        $appointment = $this->makeAppointment(AppointmentStatus::Booked);
        $this->makeVerifiedPayment($appointment, 300);

        $html = $this->pageHtml(['status' => 'all']);

        $this->assertStringContainsString('card p-3 h-100 d-flex flex-column', $html);
        $this->assertStringContainsString('mt-auto pt-3 border-top', $html);
    }
}
