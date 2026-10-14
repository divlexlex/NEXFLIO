<?php

namespace Tests\Feature;

use App\Models\BusinessHours;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Weekly AM/PM roster: in a roster week only assigned staff are bookable
 * and their slots follow the shift window; weeks without a roster behave
 * exactly as before (staff_schedules fallback + business hours).
 */
class WeeklyRosterBookingTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;

    private User $client;

    private User $amStaff;

    private User $pmStaff;

    private User $offStaff;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();

        $this->amStaff = $this->makeStaff('Amy', 'Amshift');
        $this->pmStaff = $this->makeStaff('Pam', 'Pmshift');
        $this->offStaff = $this->makeStaff('Ollie', 'Offshift');

        $this->service = Service::create([
            'name' => 'Roster Manicure (test)', 'category' => 'Nails', 'description' => 'Test',
            'price' => 179, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        foreach (range(0, 6) as $day) {
            BusinessHours::updateOrCreate(
                ['day_of_week' => $day],
                ['is_open' => true, 'open_at' => '10:00', 'close_at' => '21:00']
            );
        }
    }

    private function makeStaff(string $first, string $last): User
    {
        $user = User::create([
            'name' => "{$first} {$last}",
            'email' => strtolower("{$first}.{$last}@nexflio.test"),
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_STAFF,
        ]);
        StaffProfile::create([
            'user_id' => $user->id,
            'position' => 'Nail Technician',
            'employment_status' => 'active',
        ]);

        return $user;
    }

    private function saveRoster(string $weekStart, array $amStaff, array $pmStaff): void
    {
        $this->actingAs($this->manager)->post(route('admin.availability.weekly-roster'), [
            'week_start' => $weekStart,
            'am_start' => '10:00', 'am_end' => '16:00',
            'pm_start' => '16:00', 'pm_end' => '21:00',
            'am_staff' => $amStaff,
            'pm_staff' => $pmStaff,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    // Roster week: assigned staff listed, unassigned staff not.
    public function test_roster_week_lists_only_assigned_staff(): void
    {
        $date = now()->addDays(7)->toDateString();
        $weekStart = StaffShiftAssignment::weekStartOf($date);
        $this->saveRoster($weekStart, [$this->amStaff->id], [$this->pmStaff->id]);

        $html = $this->actingAs($this->client)->get(route('account.booking.branch.schedule', [
            'service' => $this->service->id, 'date' => $date, 'personnel' => 'any',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('Amy Amshift', $html);
        $this->assertStringContainsString('Pam Pmshift', $html);
        $this->assertStringNotContainsString('Ollie Offshift', $html);
    }

    // AM window 10:00–16:00 narrows that person's slots that week.
    public function test_assignment_window_narrows_slots(): void
    {
        $date = now()->addDays(7)->toDateString();
        $weekStart = StaffShiftAssignment::weekStartOf($date);
        $this->saveRoster($weekStart, [$this->amStaff->id], [$this->pmStaff->id]);

        $html = $this->actingAs($this->client)->get(route('account.booking.branch.schedule', [
            'service' => $this->service->id, 'date' => $date, 'personnel' => (string) $this->amStaff->id,
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('time=10%3A00', $html);
        $this->assertStringContainsString('time=15%3A30', $html);
        $this->assertStringNotContainsString('time=16%3A00', $html);
        $this->assertStringNotContainsString('time=20%3A30', $html);
    }

    // A week with no roster: everyone listed, business-hours slots.
    public function test_week_without_roster_behaves_as_before(): void
    {
        $rosterDate = now()->addDays(7)->toDateString();
        $this->saveRoster(
            StaffShiftAssignment::weekStartOf($rosterDate),
            [$this->amStaff->id],
            [$this->pmStaff->id]
        );

        $plainDate = now()->addDays(21)->toDateString();
        $this->assertNotSame(
            StaffShiftAssignment::weekStartOf($rosterDate),
            StaffShiftAssignment::weekStartOf($plainDate)
        );

        $html = $this->actingAs($this->client)->get(route('account.booking.branch.schedule', [
            'service' => $this->service->id, 'date' => $plainDate, 'personnel' => 'any',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('Amy Amshift', $html);
        $this->assertStringContainsString('Pam Pmshift', $html);
        $this->assertStringContainsString('Ollie Offshift', $html);

        $slotsHtml = $this->actingAs($this->client)->get(route('account.booking.branch.schedule', [
            'service' => $this->service->id, 'date' => $plainDate, 'personnel' => (string) $this->amStaff->id,
        ]))->assertOk()->getContent();

        // No StaffSchedule rows exist, so business hours rule the day.
        $this->assertStringContainsString('time=16%3A00', $slotsHtml);
        $this->assertStringContainsString('time=20%3A00', $slotsHtml);
    }

    // An empty save clears the roster; the week falls back to the legacy
    // staff_schedules logic instead of closing.
    public function test_empty_save_clears_the_week(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->addDays(7)->toDateString());
        $this->saveRoster($weekStart, [$this->amStaff->id], [$this->pmStaff->id]);
        $this->assertTrue(StaffShiftAssignment::weekHasRoster($weekStart));

        $this->actingAs($this->manager)->post(route('admin.availability.weekly-roster'), [
            'week_start' => $weekStart,
            'am_start' => '10:00', 'am_end' => '16:00',
            'pm_start' => '16:00', 'pm_end' => '21:00',
            'am_staff' => [],
            'pm_staff' => [],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, StaffShiftAssignment::forWeek($weekStart)->count());
        $this->assertFalse(StaffShiftAssignment::weekHasRoster($weekStart));
    }

    // Saving a week replaces its previous assignments.
    public function test_saving_a_week_replaces_assignments(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->addDays(7)->toDateString());

        $this->saveRoster($weekStart, [$this->amStaff->id], [$this->pmStaff->id]);
        $this->assertSame(['am'], StaffShiftAssignment::where('user_id', $this->amStaff->id)->forWeek($weekStart)->pluck('shift')->all());

        $this->saveRoster($weekStart, [$this->offStaff->id], []);
        $this->assertSame(1, StaffShiftAssignment::forWeek($weekStart)->count());
        $this->assertSame(
            [$this->offStaff->id],
            StaffShiftAssignment::forWeek($weekStart)->pluck('user_id')->all()
        );
    }

    // The same person on both shifts is rejected, naming them.
    public function test_same_person_on_both_shifts_is_rejected(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->addDays(7)->toDateString());

        $response = $this->actingAs($this->manager)->post(route('admin.availability.weekly-roster'), [
            'week_start' => $weekStart,
            'am_start' => '10:00', 'am_end' => '16:00',
            'pm_start' => '16:00', 'pm_end' => '21:00',
            'am_staff' => [$this->amStaff->id],
            'pm_staff' => [$this->amStaff->id],
        ]);

        $response->assertSessionHasErrors('pm_staff');
        $this->assertStringContainsString(
            'Amy Amshift',
            collect(session()->get('errors')->get('pm_staff'))->implode(' ')
        );
        $this->assertSame(0, StaffShiftAssignment::forWeek($weekStart)->count());
    }

    // Inactive (or non-staff) ids and backwards times are rejected.
    public function test_roster_validates_staff_and_times(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->addDays(7)->toDateString());

        $inactive = User::create([
            'name' => 'Ina Ctive', 'email' => 'ina.ctive@nexflio.test',
            'password' => bcrypt('password'), 'role_id' => User::ROLE_STAFF,
        ]);

        $this->actingAs($this->manager)->post(route('admin.availability.weekly-roster'), [
            'week_start' => $weekStart,
            'am_start' => '10:00', 'am_end' => '16:00',
            'pm_start' => '16:00', 'pm_end' => '21:00',
            'am_staff' => [$inactive->id],
            'pm_staff' => [],
        ])->assertSessionHasErrors('am_staff');

        $this->actingAs($this->manager)->post(route('admin.availability.weekly-roster'), [
            'week_start' => $weekStart,
            'am_start' => '16:00', 'am_end' => '10:00',
            'pm_start' => '16:00', 'pm_end' => '21:00',
        ])->assertSessionHasErrors('am_end');

        $this->assertSame(0, StaffShiftAssignment::forWeek($weekStart)->count());
    }
}
