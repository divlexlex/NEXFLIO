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

class AppointmentSameDayStartTest extends TestCase
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
            'name' => 'Same Day Facial',
            'category' => 'Facial',
            'price' => 700,
            'duration_minutes' => 60,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);
    }

    private function bookedOn(string $date): Appointment
    {
        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => $date,
            'start_time' => '10:00',
            'status' => AppointmentStatus::Booked,
            'booked_price' => 700,
        ]);
        Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => 700,
            'method' => 'gcash',
            'status' => PaymentStatus::Verified,
            'verified_by' => $this->manager->id,
            'verified_at' => now(),
        ]);

        return $appointment;
    }

    public function test_service_can_start_on_appointment_day(): void
    {
        $appointment = $this->bookedOn(now()->toDateString());

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/start")
            ->assertOk();

        $this->assertSame(AppointmentStatus::InService, $appointment->fresh()->status);
    }

    public function test_service_cannot_start_before_appointment_day(): void
    {
        $appointment = $this->bookedOn(now()->addDay()->toDateString());

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/start")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertSame(AppointmentStatus::Booked, $appointment->fresh()->status);
    }

    public function test_service_cannot_start_after_appointment_day(): void
    {
        $appointment = $this->bookedOn(now()->subDay()->toDateString());

        $this->actingAs($this->manager)
            ->patch(route('admin.appointments.status', $appointment->id), [
                'status' => AppointmentStatus::InService->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(AppointmentStatus::Booked, $appointment->fresh()->status);
    }

    public function test_calendar_hides_start_action_off_appointment_day(): void
    {
        $future = $this->bookedOn(now()->addDays(2)->toDateString());
        $today = $this->bookedOn(now()->toDateString());

        $events = $this->actingAs($this->manager)
            ->getJson(route('admin.appointments.feed'))
            ->assertOk()
            ->json();

        $futureEvent = collect($events)->firstWhere('id', $future->id);
        $todayEvent = collect($events)->firstWhere('id', $today->id);

        $this->assertNotNull($futureEvent);
        $this->assertNotNull($todayEvent);

        $futureActions = collect($futureEvent['extendedProps']['actions'] ?? [])
            ->pluck('to')
            ->all();
        $todayActions = collect($todayEvent['extendedProps']['actions'] ?? [])
            ->pluck('to')
            ->all();

        $this->assertNotContains(AppointmentStatus::InService->value, $futureActions);
        $this->assertContains(AppointmentStatus::InService->value, $todayActions);
    }

    public function test_checkout_refuses_before_appointment_day(): void
    {
        $appointment = $this->bookedOn(now()->addDay()->toDateString());

        $this->actingAs($this->manager)
            ->post(route('admin.appointments.checkout', $appointment->id), [])
            ->assertSessionHasErrors('status');

        $this->assertSame(AppointmentStatus::Booked, $appointment->fresh()->status);
    }
}
