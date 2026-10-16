<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Change-request buttons open only on verified (Booked) appointments:
 * Unverified cards show a verification notice instead, the server refuses
 * POSTs for non-Booked appointments, and rejected-payment (Cancelled)
 * cards keep their own actions with no notice.
 */
class UnverifiedChangeRequestGatingTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const NOTICE = 'Requests to reschedule or cancel will be available once your reservation fee is verified.';

    private User $manager;

    private User $staff;

    private User $client;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->client = User::create([
            'name' => 'Gating Client', 'email' => 'gating@nexflio.test',
            'password' => bcrypt('password'), 'role_id' => User::ROLE_CLIENT,
        ]);
        $this->service = Service::create([
            'name' => 'Gating Facial', 'category' => 'Facial', 'price' => 800,
            'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
    }

    private function makeAppointment(AppointmentStatus $status): Appointment
    {
        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00',
            'status' => $status,
            'booked_price' => 800,
        ]);
        Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => 300,
            'method' => 'gcash',
            'proof_path' => 'payment_proofs/test.jpg',
            'status' => $status === AppointmentStatus::Booked ? PaymentStatus::Verified : PaymentStatus::Pending,
            'verified_by' => $status === AppointmentStatus::Booked ? $this->manager->id : null,
            'verified_at' => $status === AppointmentStatus::Booked ? now() : null,
        ]);

        return $appointment;
    }

    private function cardHtml(): string
    {
        return $this->actingAs($this->client)
            ->get(route('account.bookings'))
            ->assertOk()
            ->getContent();
    }

    public function test_unverified_card_shows_notice_and_no_request_buttons(): void
    {
        $this->makeAppointment(AppointmentStatus::Unverified);

        $html = $this->cardHtml();

        $this->assertStringContainsString(self::NOTICE, $html);
        $this->assertStringNotContainsString('data-type="reschedule"', $html);
        $this->assertStringNotContainsString('data-type="cancellation"', $html);
    }

    public function test_booked_card_shows_both_buttons_and_no_notice(): void
    {
        $this->makeAppointment(AppointmentStatus::Booked);

        $html = $this->cardHtml();

        $this->assertStringContainsString('data-type="reschedule"', $html);
        $this->assertStringContainsString('data-type="cancellation"', $html);
        $this->assertStringNotContainsString(self::NOTICE, $html);
    }

    public function test_cancel_post_refused_for_unverified_appointment(): void
    {
        $appointment = $this->makeAppointment(AppointmentStatus::Unverified);

        $this->actingAs($this->client)
            ->post(route('account.bookings.change-request', $appointment), [
                'type' => 'cancellation',
                'reason_category' => 'change_of_plans',
            ])
            ->assertSessionHasErrors('type');

        $this->assertDatabaseCount('appointment_change_requests', 0);
        $this->assertSame(AppointmentStatus::Unverified, $appointment->fresh()->status);
    }

    public function test_reschedule_post_refused_for_unverified_appointment(): void
    {
        $appointment = $this->makeAppointment(AppointmentStatus::Unverified);

        $this->actingAs($this->client)
            ->post(route('account.bookings.change-request', $appointment), [
                'type' => 'reschedule',
                'requested_date' => now()->addDays(6)->toDateString(),
                'requested_start_time' => '14:30',
            ])
            ->assertSessionHasErrors('type');

        $this->assertDatabaseCount('appointment_change_requests', 0);
    }

    public function test_booked_posts_still_work_for_both_types(): void
    {
        $cancel = $this->makeAppointment(AppointmentStatus::Booked);

        $this->actingAs($this->client)
            ->post(route('account.bookings.change-request', $cancel), [
                'type' => 'cancellation',
                'reason_category' => 'schedule_conflict',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $resched = $this->makeAppointment(AppointmentStatus::Booked);
        $resched->update(['start_time' => '11:00']);

        $this->actingAs($this->client)
            ->post(route('account.bookings.change-request', $resched), [
                'type' => 'reschedule',
                'requested_date' => now()->addDays(6)->toDateString(),
                'requested_start_time' => '14:30',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('appointment_change_requests', 2);
    }

    public function test_rejected_cards_keep_actions_and_show_no_notice(): void
    {
        $topup = $this->makeAppointment(AppointmentStatus::Unverified);
        $this->actingAs($this->manager)->patch(
            route('admin.payments.reject', $topup->payment->id),
            ['rejection_category' => 'insufficient_amount', 'amount_received' => 200]
        )->assertRedirect();

        $resubmit = $this->makeAppointment(AppointmentStatus::Unverified);
        $resubmit->update(['start_time' => '11:00']);
        $this->actingAs($this->manager)->patch(
            route('admin.payments.reject', $resubmit->payment->id),
            ['rejection_category' => 'invalid_proof']
        )->assertRedirect();

        $html = $this->cardHtml();

        $this->assertStringContainsString('Continue to book this', $html);
        $this->assertStringContainsString('Send a new proof', $html);
        $this->assertStringNotContainsString(self::NOTICE, $html);
    }
}
