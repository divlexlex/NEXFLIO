<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BusinessHours;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Admin Walk-in Entry closing guard.
 *
 * The modal now warns before submit (a client-side mirror of
 * BusinessHours::windowForDay() + slotStartAllowed()), and the validation
 * `$errors` banner on the Appointments page auto-dismisses like every other
 * flash banner. Neither change alters the server rule — these tests pin the
 * server behavior and the data plumbing the modal script depends on.
 */
class WalkInClosingGuardTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;

    private User $staff;

    private Service $service;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();

        // Massage service → the seeded staff is a Massage Technician, so the
        // category-eligibility check passes. 60 minutes keeps the arithmetic
        // simple against the 09:00–18:00 window set below.
        $this->service = Service::create([
            'name' => 'Guard Massage',
            'category' => 'Massage',
            'price' => 500,
            'duration_minutes' => 60,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);

        // Pin the target day's window so "past closing" is deterministic:
        // 09:00–18:00, same-day (close > open, no overnight rollover).
        $this->date = now()->addDay()->toDateString();
        BusinessHours::where('day_of_week', Carbon::parse($this->date)->dayOfWeek)
            ->update(['open_at' => '09:00:00', 'close_at' => '18:00:00', 'is_open' => true]);
    }

    public function test_walk_in_past_closing_is_refused_and_index_shows_dismissible_banner(): void
    {
        // 17:30 + 60 min = 18:30 — the service would run past the 18:00 close.
        $response = $this->actingAs($this->manager)
            ->from(route('admin.appointments'))
            ->post(route('admin.appointments.walk-in'), [
                'walk_in_name' => 'Late Guest',
                'service_id' => $this->service->id,
                'personnel_id' => $this->staff->id,
                'appointment_date' => $this->date,
                'start_time' => '17:30',
            ]);

        $response->assertRedirect(route('admin.appointments'))
            ->assertSessionHasErrors([
                'start_time' => 'That time is past shop closing. Please choose an earlier slot, or book for another day.',
            ]);

        $this->assertSame(0, Appointment::count());

        // Follow to the Appointments index: this flow sets no session flash,
        // so a dismissible danger alert can only be the fixed $errors block.
        $html = $this->actingAs($this->manager)
            ->from(route('admin.appointments'))
            ->followingRedirects()
            ->post(route('admin.appointments.walk-in'), [
                'walk_in_name' => 'Late Guest',
                'service_id' => $this->service->id,
                'personnel_id' => $this->staff->id,
                'appointment_date' => $this->date,
                'start_time' => '17:30',
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('alert alert-danger alert-dismissible', $html);
        $this->assertStringContainsString('data-nx-flash', $html);
        $this->assertStringContainsString(
            'That time is past shop closing. Please choose an earlier slot, or book for another day.',
            $html
        );
    }

    public function test_index_exposes_business_hours_and_service_durations_for_the_modal(): void
    {
        $this->actingAs($this->manager)
            ->get(route('admin.appointments'))
            ->assertOk()
            ->assertSee('nxBusinessHours', false)
            ->assertSee('"duration_minutes"', false);
    }

    public function test_walk_in_inside_the_window_is_created(): void
    {
        // 09:00 + 60 min = 10:00 — comfortably inside 09:00–18:00.
        $this->actingAs($this->manager)
            ->post(route('admin.appointments.walk-in'), [
                'walk_in_name' => 'On Time Guest',
                'service_id' => $this->service->id,
                'personnel_id' => $this->staff->id,
                'appointment_date' => $this->date,
                'start_time' => '09:00',
            ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, Appointment::count());

        $appointment = Appointment::firstOrFail();
        $this->assertSame('On Time Guest', $appointment->walk_in_name);
        $this->assertNull($appointment->user_id);
    }
}
