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
 * Weekly individual roster: in a roster week only assigned staff are
 * bookable and their slots follow their own shift window; weeks without a
 * roster behave exactly as before (staff_schedules fallback + business
 * hours).
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

    private function saveRoster(string $weekStart, array $rows): void
    {
        $this->actingAs($this->manager)->post(route('admin.availability.weekly-roster'), [
            'week_start' => $weekStart,
            'assignments' => $rows,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function amRow(int $userId): array
    {
        return ['user_id' => $userId, 'start_time' => '10:00', 'end_time' => '16:00'];
    }

    private function pmRow(int $userId): array
    {
        return ['user_id' => $userId, 'start_time' => '16:00', 'end_time' => '21:00'];
    }

    // Roster week: assigned staff listed, unassigned staff not.
    public function test_roster_week_lists_only_assigned_staff(): void
    {
        $date = now()->addDays(7)->toDateString();
        $weekStart = StaffShiftAssignment::weekStartOf($date);
        $this->saveRoster($weekStart, [$this->amRow($this->amStaff->id), $this->pmRow($this->pmStaff->id)]);

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
        $this->saveRoster($weekStart, [$this->amRow($this->amStaff->id), $this->pmRow($this->pmStaff->id)]);

        $html = $this->actingAs($this->client)->get(route('account.booking.branch.schedule', [
            'service' => $this->service->id, 'date' => $date, 'personnel' => (string) $this->amStaff->id,
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('data-slot-time="10:00"', $html);
        $this->assertStringContainsString('data-slot-time="15:30"', $html);
        $this->assertStringNotContainsString('data-slot-time="16:00"', $html);
        $this->assertStringNotContainsString('data-slot-time="20:30"', $html);
    }

    // A week with no roster: everyone listed, business-hours slots.
    public function test_week_without_roster_behaves_as_before(): void
    {
        $rosterDate = now()->addDays(7)->toDateString();
        $this->saveRoster(
            StaffShiftAssignment::weekStartOf($rosterDate),
            [$this->amRow($this->amStaff->id), $this->pmRow($this->pmStaff->id)]
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
        $this->assertStringContainsString('data-slot-time="16:00"', $slotsHtml);
        $this->assertStringContainsString('data-slot-time="20:00"', $slotsHtml);
    }

    // An empty save clears the roster; the week falls back to the legacy
    // staff_schedules logic instead of closing.
    public function test_empty_save_clears_the_week(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->addDays(7)->toDateString());
        $this->saveRoster($weekStart, [$this->amRow($this->amStaff->id), $this->pmRow($this->pmStaff->id)]);
        $this->assertTrue(StaffShiftAssignment::weekHasRoster($weekStart));

        $this->actingAs($this->manager)->post(route('admin.availability.weekly-roster'), [
            'week_start' => $weekStart,
            'assignments' => [],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, StaffShiftAssignment::forWeek($weekStart)->count());
        $this->assertFalse(StaffShiftAssignment::weekHasRoster($weekStart));
    }

    // Saving a week replaces its previous assignments, each with their
    // own times.
    public function test_saving_a_week_replaces_assignments(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->addDays(7)->toDateString());

        $this->saveRoster($weekStart, [$this->amRow($this->amStaff->id), $this->pmRow($this->pmStaff->id)]);
        $this->assertSame(2, StaffShiftAssignment::forWeek($weekStart)->count());
        $this->assertSame(
            '10:00',
            substr(StaffShiftAssignment::where('user_id', $this->amStaff->id)->forWeek($weekStart)->value('start_time'), 0, 5)
        );

        $this->saveRoster($weekStart, [['user_id' => $this->offStaff->id, 'start_time' => '11:00', 'end_time' => '19:00']]);
        $this->assertSame(1, StaffShiftAssignment::forWeek($weekStart)->count());
        $this->assertSame(
            [$this->offStaff->id],
            StaffShiftAssignment::forWeek($weekStart)->pluck('user_id')->all()
        );
    }

    // Copy previous week reproduces the individual rows and times.
    public function test_copy_previous_week_reproduces_individual_times(): void
    {
        $prevWeek = StaffShiftAssignment::weekStartOf(now()->addDays(7)->toDateString());
        $thisWeek = StaffShiftAssignment::weekStartOf(now()->addDays(14)->toDateString());
        $this->assertNotSame($prevWeek, $thisWeek);

        $this->saveRoster($prevWeek, [
            ['user_id' => $this->amStaff->id, 'start_time' => '09:00', 'end_time' => '15:00'],
            ['user_id' => $this->pmStaff->id, 'start_time' => '15:00', 'end_time' => '21:00'],
        ]);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.availability', ['week' => $thisWeek, 'copy_from' => $prevWeek]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Amy Amshift', $html);
        $this->assertStringContainsString('Pam Pmshift', $html);

        // Saving the prefilled week writes the copied individual times there.
        $this->actingAs($this->manager)->post(route('admin.availability.weekly-roster'), [
            'week_start' => $thisWeek,
            'assignments' => [
                ['user_id' => $this->amStaff->id, 'start_time' => '09:00', 'end_time' => '15:00'],
                ['user_id' => $this->pmStaff->id, 'start_time' => '15:00', 'end_time' => '21:00'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(
            '09:00',
            substr(StaffShiftAssignment::where('user_id', $this->amStaff->id)->forWeek($thisWeek)->value('start_time'), 0, 5)
        );
        $this->assertSame(2, StaffShiftAssignment::forWeek($thisWeek)->count());
    }

    // The same person twice in one payload is rejected, naming them.
    public function test_duplicate_person_in_payload_is_rejected(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->addDays(7)->toDateString());

        $response = $this->actingAs($this->manager)->post(route('admin.availability.weekly-roster'), [
            'week_start' => $weekStart,
            'assignments' => [
                $this->amRow($this->amStaff->id),
                $this->pmRow($this->amStaff->id),
            ],
        ]);

        $response->assertSessionHasErrors('assignments');
        $this->assertStringContainsString(
            'Amy Amshift',
            collect(session()->get('errors')->get('assignments'))->implode(' ')
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
            'assignments' => [
                ['user_id' => $inactive->id, 'start_time' => '10:00', 'end_time' => '16:00'],
            ],
        ])->assertSessionHasErrors('assignments');

        $this->actingAs($this->manager)->post(route('admin.availability.weekly-roster'), [
            'week_start' => $weekStart,
            'assignments' => [
                ['user_id' => $this->amStaff->id, 'start_time' => '16:00', 'end_time' => '10:00'],
            ],
        ])->assertSessionHasErrors('assignments');

        $this->assertSame(0, StaffShiftAssignment::forWeek($weekStart)->count());
    }

    // The roster tab renders individual rows with no AM/PM cards: category
    // pills plus the grouped Not-assigned strip.
    public function test_roster_tab_renders_individual_rows(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->addDays(7)->toDateString());
        $this->saveRoster($weekStart, [$this->amRow($this->amStaff->id)]);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.availability', ['week' => $weekStart]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Amy Amshift', $html);
        $this->assertStringContainsString('Nail Technician', $html);
        $this->assertStringContainsString('Not assigned — not bookable this week', $html);
        $this->assertStringContainsString('Pam Pmshift', $html);
        $this->assertStringNotContainsString('AM — Morning shift', $html);
        $this->assertStringNotContainsString('PM — Afternoon shift', $html);
        $this->assertStringNotContainsString('am_staff', $html);
        $this->assertStringNotContainsString('pm_staff', $html);
    }

    // The Master Schedule badge shows times without any AM/PM label.
    public function test_master_schedule_badge_shows_times_only(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->addDays(7)->toDateString());
        $this->saveRoster($weekStart, [$this->amRow($this->amStaff->id)]);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.master-schedule', ['week' => $weekStart]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('10:00 AM – 4:00 PM', $html);
    }
}
