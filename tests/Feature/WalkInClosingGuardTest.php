<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BusinessHours;
use App\Models\Service;
use App\Models\User;
use App\Support\WalkInHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Admin Walk-in Entry closing guard (walk-in-only rule).
 *
 * The modal mirrors App\Support\WalkInHours client-side; the server re-checks
 * on submit. Online bookings still use BusinessHours::startFitsOnDate().
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
        // category-eligibility check passes.
        $this->service = Service::create([
            'name' => 'Guard Massage',
            'category' => 'Massage',
            'price' => 500,
            'duration_minutes' => 60,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);

        // Open noon; shop BusinessHours close is irrelevant to walk-in finish
        // cutoff (config walk_in.close_at, default 01:00).
        $this->date = now()->toDateString();
        BusinessHours::where('day_of_week', Carbon::parse($this->date)->dayOfWeek)
            ->update(['open_at' => '12:00:00', 'close_at' => '22:00:00', 'is_open' => true]);

        config(['booking.walk_in.close_at' => '01:00']);
    }

    public function test_walk_in_past_closing_is_refused_and_index_shows_dismissible_banner(): void
    {
        // 12:01 AM + 60 min = 1:01 AM — past the 1:00 AM walk-in close.
        $response = $this->actingAs($this->manager)
            ->from(route('admin.appointments'))
            ->post(route('admin.appointments.walk-in'), [
                'walk_in_name' => 'Late Guest',
                'service_id' => $this->service->id,
                'personnel_id' => $this->staff->id,
                'appointment_date' => $this->date,
                'start_time' => '00:01',
            ]);

        $response->assertRedirect(route('admin.appointments'))
            ->assertSessionHasErrors([
                'start_time' => 'That time is past walk-in closing. Please choose an earlier slot, or book for another day.',
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
                'start_time' => '00:01',
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('alert alert-danger alert-dismissible', $html);
        $this->assertStringContainsString('data-nx-flash', $html);
        $this->assertStringContainsString(
            'That time is past walk-in closing. Please choose an earlier slot, or book for another day.',
            $html
        );
    }

    public function test_index_exposes_business_hours_and_service_durations_for_the_modal(): void
    {
        $this->actingAs($this->manager)
            ->get(route('admin.appointments'))
            ->assertOk()
            ->assertSee('nxBusinessHours', false)
            ->assertSee('nxWalkInCloseAt', false)
            ->assertSee('"duration_minutes"', false);
    }

    public function test_walk_in_inside_the_window_is_created(): void
    {
        // 12:00 PM + 60 min — inside open → walk-in close.
        $this->actingAs($this->manager)
            ->post(route('admin.appointments.walk-in'), [
                'walk_in_name' => 'On Time Guest',
                'service_id' => $this->service->id,
                'personnel_id' => $this->staff->id,
                'appointment_date' => $this->date,
                'start_time' => '12:00',
            ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, Appointment::count());

        $appointment = Appointment::firstOrFail();
        $this->assertSame('On Time Guest', $appointment->walk_in_name);
        $this->assertNull($appointment->user_id);
    }

    public function test_walk_in_allows_finish_at_configured_close(): void
    {
        $this->assertTrue(WalkInHours::startFitsOnDate($this->date, '00:00', 60));

        $this->actingAs($this->manager)
            ->post(route('admin.appointments.walk-in'), [
                'walk_in_name' => 'Last Slot Guest',
                'service_id' => $this->service->id,
                'personnel_id' => $this->staff->id,
                'appointment_date' => $this->date,
                'start_time' => '00:00',
            ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('appointments', ['walk_in_name' => 'Last Slot Guest']);
    }
}
