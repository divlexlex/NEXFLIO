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
 * Payments card touches: refund/no-refund remark for appointments cancelled
 * through an approved change request, and verified row pills rendered as a
 * check icon instead of text.
 */
class PaymentCardRemarkTest extends TestCase
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
            'name' => 'Remark Facial (test)', 'category' => 'Facial', 'description' => 'Test',
            'price' => 800, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
    }

    private function cancelledWithPaid(array $paymentOverrides = []): Appointment
    {
        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'status' => AppointmentStatus::Cancelled,
            'booked_price' => 800,
        ]);
        Payment::create(array_merge([
            'appointment_id' => $appointment->id,
            'amount' => 300,
            'method' => 'gcash',
            'status' => PaymentStatus::Verified,
            'verified_by' => $this->manager->id,
            'verified_at' => now(),
        ], $paymentOverrides));

        AppointmentChangeRequest::create([
            'appointment_id' => $appointment->id,
            'requested_by' => $this->client->id,
            'type' => AppointmentChangeRequest::TYPE_CANCELLATION,
            'status' => AppointmentChangeRequest::STATUS_APPROVED,
            'reviewed_by' => $this->manager->id,
        ]);

        return $appointment;
    }

    private function cardHtml(): string
    {
        return $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all']))
            ->assertOk()
            ->getContent();
    }

    public function test_refund_remark_with_existing_refund_lines_intact(): void
    {
        // Legacy in-flight shape: rejected primary (refund requested) plus
        // the verified partial receipt holding the money.
        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'status' => AppointmentStatus::Cancelled,
            'booked_price' => 800,
        ]);
        Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => 300,
            'method' => 'gcash',
            'status' => PaymentStatus::Rejected,
            'rejection_category' => 'insufficient_amount',
            'amount_received' => 200,
            'refund_status' => 'requested',
            'refund_requested_at' => now(),
        ]);
        Payment::create([
            'appointment_id' => $appointment->id,
            'kind' => 'partial_receipt',
            'amount' => 200,
            'method' => 'gcash',
            'status' => PaymentStatus::Verified,
            'verified_by' => $this->manager->id,
            'verified_at' => now(),
        ]);
        AppointmentChangeRequest::create([
            'appointment_id' => $appointment->id,
            'requested_by' => $this->client->id,
            'type' => AppointmentChangeRequest::TYPE_CANCELLATION,
            'status' => AppointmentChangeRequest::STATUS_APPROVED,
            'reviewed_by' => $this->manager->id,
        ]);

        $html = $this->cardHtml();

        $this->assertStringContainsString('>Refund</span>', $html);
        $this->assertStringContainsString('Mark refund sent', $html);
        $this->assertStringContainsString('Refund requested: ₱200.00', $html);
    }

    public function test_no_refund_remark_without_refund_in_flight(): void
    {
        $this->cancelledWithPaid();

        $html = $this->cardHtml();

        $this->assertStringContainsString('>No refund</span>', $html);
        $this->assertStringNotContainsString('>Refund</span>', $html);
        $this->assertStringNotContainsString('Mark refund sent', $html);
    }

    public function test_no_remark_without_request_or_without_payment(): void
    {
        // Cancelled with an approved request but nothing ever paid.
        $unpaid = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '11:00',
            'status' => AppointmentStatus::Cancelled,
            'booked_price' => 800,
        ]);
        AppointmentChangeRequest::create([
            'appointment_id' => $unpaid->id,
            'requested_by' => $this->client->id,
            'type' => AppointmentChangeRequest::TYPE_CANCELLATION,
            'status' => AppointmentChangeRequest::STATUS_APPROVED,
            'reviewed_by' => $this->manager->id,
        ]);
        // Nothing ever paid: helper stays null even with an approved request.
        $this->assertNull($unpaid->cancellationRefundRemark());

        // Cancelled with money paid but no change request at all.
        $noRequest = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '12:00',
            'status' => AppointmentStatus::Cancelled,
            'booked_price' => 800,
        ]);
        Payment::create([
            'appointment_id' => $noRequest->id,
            'amount' => 300,
            'method' => 'gcash',
            'status' => PaymentStatus::Verified,
            'verified_by' => $this->manager->id,
            'verified_at' => now(),
        ]);

        $html = $this->cardHtml();

        $this->assertStringNotContainsString('>Refund</span>', $html);
        $this->assertStringNotContainsString('>No refund</span>', $html);
    }

    public function test_verified_pills_become_checks_pending_stays_text(): void
    {
        $this->cancelledWithPaid();

        $pending = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '13:00',
            'status' => AppointmentStatus::Unverified,
            'booked_price' => 800,
        ]);
        Payment::create([
            'appointment_id' => $pending->id,
            'amount' => 300,
            'method' => 'gcash',
            'proof_path' => 'payment_proofs/test.jpg',
            'status' => PaymentStatus::Pending,
        ]);

        $html = $this->cardHtml();

        $this->assertStringNotContainsString('>Verified</span>', $html);
        $this->assertStringContainsString('bi-check-circle-fill', $html);
        $this->assertStringContainsString('title="Verified"', $html);
        $this->assertStringContainsString('aria-label="Verified"', $html);
        $this->assertStringContainsString('>Pending</span>', $html);
    }
}
