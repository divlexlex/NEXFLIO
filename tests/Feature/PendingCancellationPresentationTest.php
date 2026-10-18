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
 * Pending cancellation requests present the appointment as Pending on the
 * admin calendar (colour + drawer notice, no operation buttons) without
 * moving its stored status, and block the move to In-Service until the
 * request is approved, rejected, or withdrawn.
 */
class PendingCancellationPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;

    private User $staff;

    private User $client;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->service = Service::create([
            'name' => 'Pending Cancel Facial', 'category' => 'Facial', 'price' => 800,
            'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
    }

    private function booking(AppointmentStatus $status = AppointmentStatus::Booked): Appointment
    {
        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->toDateString(),
            'start_time' => '10:00',
            'status' => $status,
        ]);
        Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => $this->service->price,
            'method' => 'gcash',
            'status' => $status === AppointmentStatus::Unverified ? PaymentStatus::Pending : PaymentStatus::Verified,
        ]);

        return $appointment;
    }

    private function requestCancellation(Appointment $appointment, string $reason = 'Change of plans'): AppointmentChangeRequest
    {
        return AppointmentChangeRequest::create([
            'appointment_id' => $appointment->id,
            'requested_by' => $this->client->id,
            'type' => AppointmentChangeRequest::TYPE_CANCELLATION,
            'status' => AppointmentChangeRequest::STATUS_PENDING,
            'reason' => $reason,
        ]);
    }

    private function feedEvent(int $appointmentId): array
    {
        $data = $this->actingAs($this->manager)->getJson(route('admin.appointments.feed'))->assertOk()->json();
        $event = collect($data)->firstWhere('id', $appointmentId);
        $this->assertNotNull($event);

        return $event;
    }

    public function test_pending_cancellation_flags_event_with_pending_colour_and_no_actions(): void
    {
        $appointment = $this->booking();
        $this->requestCancellation($appointment);

        $event = $this->feedEvent($appointment->id);

        $this->assertTrue($event['extendedProps']['pending_cancellation']);
        $this->assertSame('Change of plans', $event['extendedProps']['cancellation_reason']);
        // Exact Pending (Unverified) triple + class, overriding Booked green.
        $this->assertSame('#fcebd2', $event['backgroundColor']);
        $this->assertSame('#ecc78f', $event['borderColor']);
        $this->assertSame('#8a5a16', $event['textColor']);
        $this->assertSame(['nx-evt', 'nx-evt-unverified'], $event['classNames']);
        $this->assertSame([], $event['extendedProps']['actions']);
        // Stored status untouched.
        $this->assertSame('booked', $event['extendedProps']['status']);
        $this->assertSame(AppointmentStatus::Booked, $appointment->fresh()->status);
    }

    public function test_approving_returns_the_event_to_its_normal_colour(): void
    {
        $appointment = $this->booking();
        $req = $this->requestCancellation($appointment);

        $this->actingAs($this->manager)
            ->patch(route('admin.appointment-requests.review', $req), [
                'decision' => 'approve',
                'refund_decision' => 'non_refundable',
            ])
            ->assertRedirect();

        $event = $this->feedEvent($appointment->id);

        $this->assertFalse($event['extendedProps']['pending_cancellation']);
        $this->assertSame('#ececec', $event['backgroundColor']);
    }

    public function test_withdrawn_request_returns_the_event_to_its_normal_colour(): void
    {
        $appointment = $this->booking();
        $req = $this->requestCancellation($appointment);

        $this->actingAs($this->client)
            ->delete(route('account.bookings.change-request.withdraw', [$appointment, $req]))
            ->assertRedirect();

        $event = $this->feedEvent($appointment->id);

        $this->assertFalse($event['extendedProps']['pending_cancellation']);
        $this->assertSame('#dcf0e2', $event['backgroundColor']);
        $this->assertNotSame([], $event['extendedProps']['actions']);
    }

    public function test_rejected_request_returns_the_event_to_its_normal_colour(): void
    {
        $appointment = $this->booking();
        $req = $this->requestCancellation($appointment);

        $this->actingAs($this->manager)
            ->patch(route('admin.appointment-requests.review', $req), ['decision' => 'reject', 'review_note' => 'Too late to cancel.'])
            ->assertRedirect();

        $event = $this->feedEvent($appointment->id);

        $this->assertFalse($event['extendedProps']['pending_cancellation']);
        $this->assertSame('#dcf0e2', $event['backgroundColor']);
    }

    public function test_drawer_shows_pending_notice_with_reason_and_review_link(): void
    {
        $appointment = $this->booking();
        $this->requestCancellation($appointment, 'Family emergency');

        $html = $this->actingAs($this->manager)->get(route('admin.appointments'))->assertOk()->getContent();

        $this->assertStringContainsString('Cancellation requested — pending review in Change Requests.', $html);
        $this->assertStringContainsString('Review in Change Requests', $html);
        $this->assertStringContainsString(route('admin.appointment-requests'), $html);
        $this->assertStringContainsString('Pending — cancellation requested', $html);
    }

    public function test_in_service_refused_while_cancellation_pending(): void
    {
        $appointment = $this->booking();
        $this->requestCancellation($appointment);

        $this->actingAs($this->manager)->patch(
            route('admin.appointments.status', $appointment->id),
            ['status' => AppointmentStatus::InService->value]
        )->assertSessionHasErrors('status');

        $this->assertSame(AppointmentStatus::Booked, $appointment->fresh()->status);
    }

    public function test_reschedule_pending_changes_nothing(): void
    {
        $appointment = $this->booking();
        AppointmentChangeRequest::create([
            'appointment_id' => $appointment->id,
            'requested_by' => $this->client->id,
            'type' => AppointmentChangeRequest::TYPE_RESCHEDULE,
            'status' => AppointmentChangeRequest::STATUS_PENDING,
            'requested_date' => now()->addDays(8)->toDateString(),
            'requested_start_time' => '16:00',
        ]);

        $event = $this->feedEvent($appointment->id);

        $this->assertFalse($event['extendedProps']['pending_cancellation']);
        $this->assertSame('#dcf0e2', $event['backgroundColor']);
        $this->assertNotSame([], $event['extendedProps']['actions']);

        $this->actingAs($this->manager)->patch(
            route('admin.appointments.status', $appointment->id),
            ['status' => AppointmentStatus::InService->value]
        )->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(AppointmentStatus::InService, $appointment->fresh()->status);
    }
}
