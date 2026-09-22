<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;

    private User $staff;

    private User $client;

    private User $otherClient;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->otherClient = User::create([
            'name' => 'Other Client', 'email' => 'client2@nexflio.test',
            'password' => bcrypt('password'), 'role_id' => User::ROLE_CLIENT,
        ]);
        $this->service = Service::create([
            'name' => 'Classic Manicure', 'category' => 'Nails', 'price' => 500,
            'duration_minutes' => 45, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
    }

    private function booking(AppointmentStatus $status = AppointmentStatus::Booked, ?User $owner = null): Appointment
    {
        $appointment = Appointment::create([
            'user_id' => ($owner ?? $this->client)->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDays(3)->toDateString(),
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

    public function test_client_can_request_a_cancellation_without_touching_the_appointment(): void
    {
        $appointment = $this->booking();

        $this->actingAs($this->client)
            ->post(route('account.bookings.change-request', $appointment), ['type' => 'cancellation', 'reason' => 'Sick'])
            ->assertRedirect();

        $this->assertDatabaseHas('appointment_change_requests', [
            'appointment_id' => $appointment->id,
            'type' => 'cancellation',
            'status' => 'pending',
            'requested_by' => $this->client->id,
        ]);
        $this->assertSame(AppointmentStatus::Booked, $appointment->fresh()->status);
    }

    public function test_client_can_request_a_reschedule_with_a_proposed_slot(): void
    {
        $appointment = $this->booking();
        $newDate = now()->addDays(6)->toDateString();

        $this->actingAs($this->client)
            ->post(route('account.bookings.change-request', $appointment), [
                'type' => 'reschedule',
                'requested_date' => $newDate,
                'requested_start_time' => '14:30',
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('appointment_change_requests', [
            'appointment_id' => $appointment->id,
            'type' => 'reschedule',
            'requested_date' => $newDate.' 00:00:00',
            'requested_start_time' => '14:30',
            'status' => 'pending',
        ]);
    }

    public function test_reschedule_request_requires_a_date_and_time(): void
    {
        $appointment = $this->booking();

        $this->actingAs($this->client)
            ->post(route('account.bookings.change-request', $appointment), ['type' => 'reschedule'])
            ->assertSessionHasErrors(['requested_date', 'requested_start_time']);
    }

    public function test_cannot_request_a_change_on_a_completed_appointment(): void
    {
        $appointment = $this->booking(AppointmentStatus::Completed);

        $this->actingAs($this->client)
            ->post(route('account.bookings.change-request', $appointment), ['type' => 'cancellation'])
            ->assertSessionHasErrors('type');

        $this->assertDatabaseCount('appointment_change_requests', 0);
    }

    public function test_only_one_pending_request_per_appointment(): void
    {
        $appointment = $this->booking();
        AppointmentChangeRequest::create([
            'appointment_id' => $appointment->id, 'requested_by' => $this->client->id,
            'type' => 'cancellation', 'status' => 'pending',
        ]);

        $this->actingAs($this->client)
            ->post(route('account.bookings.change-request', $appointment), ['type' => 'cancellation'])
            ->assertSessionHasErrors('type');

        $this->assertDatabaseCount('appointment_change_requests', 1);
    }

    public function test_client_cannot_request_a_change_on_someone_elses_appointment(): void
    {
        $appointment = $this->booking(owner: $this->otherClient);

        $this->actingAs($this->client)
            ->post(route('account.bookings.change-request', $appointment), ['type' => 'cancellation'])
            ->assertForbidden();
    }

    public function test_manager_approving_a_cancellation_cancels_the_appointment(): void
    {
        $appointment = $this->booking();
        $req = AppointmentChangeRequest::create([
            'appointment_id' => $appointment->id, 'requested_by' => $this->client->id,
            'type' => 'cancellation', 'status' => 'pending', 'reason' => 'Change of plans',
        ]);

        $this->actingAs($this->manager)
            ->patch(route('admin.appointment-requests.review', $req), ['decision' => 'approve'])
            ->assertRedirect();

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertSame('approved', $req->fresh()->status);
        $this->assertSame($this->manager->id, $req->fresh()->reviewed_by);
    }

    public function test_manager_approving_a_reschedule_moves_the_appointment(): void
    {
        $appointment = $this->booking();
        $newDate = now()->addDays(8)->toDateString();
        $req = AppointmentChangeRequest::create([
            'appointment_id' => $appointment->id, 'requested_by' => $this->client->id,
            'type' => 'reschedule', 'status' => 'pending',
            'requested_date' => $newDate, 'requested_start_time' => '16:00',
        ]);

        $this->actingAs($this->manager)
            ->patch(route('admin.appointment-requests.review', $req), ['decision' => 'approve'])
            ->assertRedirect();

        $fresh = $appointment->fresh();
        $this->assertSame($newDate, $fresh->appointment_date->toDateString());
        $this->assertSame('16:00', substr($fresh->start_time, 0, 5));
        $this->assertSame('approved', $req->fresh()->status);
    }

    public function test_manager_cannot_approve_a_reschedule_into_a_taken_slot(): void
    {
        $appointment = $this->booking();
        $clashDate = now()->addDays(9)->toDateString();

        // Existing appointment already holding that exact slot for the same staff.
        Appointment::create([
            'user_id' => $this->otherClient->id, 'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id, 'appointment_date' => $clashDate,
            'start_time' => '11:00', 'status' => AppointmentStatus::Booked,
        ]);

        $req = AppointmentChangeRequest::create([
            'appointment_id' => $appointment->id, 'requested_by' => $this->client->id,
            'type' => 'reschedule', 'status' => 'pending',
            'requested_date' => $clashDate, 'requested_start_time' => '11:00',
        ]);

        $this->actingAs($this->manager)
            ->patch(route('admin.appointment-requests.review', $req), ['decision' => 'approve'])
            ->assertSessionHasErrors('decision');

        $this->assertSame('pending', $req->fresh()->status);
        $this->assertSame(now()->addDays(3)->toDateString(), $appointment->fresh()->appointment_date->toDateString());
    }

    public function test_manager_rejecting_leaves_the_appointment_and_notifies_the_client(): void
    {
        $appointment = $this->booking();
        $req = AppointmentChangeRequest::create([
            'appointment_id' => $appointment->id, 'requested_by' => $this->client->id,
            'type' => 'cancellation', 'status' => 'pending',
        ]);

        $this->actingAs($this->manager)
            ->patch(route('admin.appointment-requests.review', $req), ['decision' => 'reject', 'review_note' => 'Too close to the date'])
            ->assertRedirect();

        $this->assertSame('rejected', $req->fresh()->status);
        $this->assertSame(AppointmentStatus::Booked, $appointment->fresh()->status);
        $this->assertTrue(
            Notification::where('user_id', $this->client->id)->where('title', 'like', '%declined%')->exists()
        );
    }

    public function test_client_can_withdraw_a_pending_request(): void
    {
        $appointment = $this->booking();
        $req = AppointmentChangeRequest::create([
            'appointment_id' => $appointment->id, 'requested_by' => $this->client->id,
            'type' => 'cancellation', 'status' => 'pending',
        ]);

        $this->actingAs($this->client)
            ->delete(route('account.bookings.change-request.withdraw', [$appointment, $req]))
            ->assertRedirect();

        $this->assertDatabaseMissing('appointment_change_requests', ['id' => $req->id]);
    }

    public function test_client_cannot_reach_the_manager_review_queue(): void
    {
        $this->actingAs($this->client)->get(route('admin.appointment-requests'))->assertForbidden();
    }
}
