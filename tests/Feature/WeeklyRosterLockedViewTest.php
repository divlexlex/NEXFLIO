<?php

namespace Tests\Feature;

use App\Models\StaffProfile;
use App\Models\StaffShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Weekly roster locked/read-only view: a week that already has saved
 * assignments opens read-only with an enabled "Edit" button; the editable
 * form only shows for a week with no roster yet or when ?edit=1 is present.
 */
class WeeklyRosterLockedViewTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;

    private User $amStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->amStaff = $this->makeStaff('Amy', 'Amshift');
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

    private function weekStart(int $daysAhead): string
    {
        return StaffShiftAssignment::weekStartOf(now()->addDays($daysAhead)->toDateString());
    }

    private function saveRoster(string $weekStart, array $rows): void
    {
        $this->actingAs($this->manager)->post(route('admin.availability.weekly-roster'), [
            'week_start' => $weekStart,
            'assignments' => $rows,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    private const DISABLED_EDIT = '<button type="button" class="btn btn-sm btn-secondary" disabled>';

    public function test_saved_week_renders_locked_read_only_by_default(): void
    {
        $week = $this->weekStart(7);
        $this->saveRoster($week, [
            ['user_id' => $this->amStaff->id, 'start_time' => '10:00', 'end_time' => '16:00'],
        ]);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.availability', ['week' => $week]))
            ->assertOk()
            ->getContent();

        // The shift times are plain 12-hour text and the staff row is present.
        $this->assertStringContainsString('<span class="roster-shift-time">10:00 AM</span>', $html);
        $this->assertStringContainsString('<span class="roster-shift-time">4:00 PM</span>', $html);
        $this->assertStringContainsString('Amy Amshift', $html);

        // No editable controls for this roster form.
        $this->assertStringNotContainsString('name="assignments[', $html);
        $this->assertStringNotContainsString('+ Add staff', $html);
        $this->assertStringNotContainsString('Save Roster', $html);
        $this->assertStringNotContainsString('Copy previous week', $html);
        $this->assertStringNotContainsString('>Cancel</a>', $html);

        // The Not-assigned strip still renders.
        $this->assertStringContainsString('Not assigned — not bookable this week', $html);

        // An enabled Edit button opens the same week in edit mode.
        $editUrl = e(route('admin.availability', ['week' => $week, 'edit' => 1]).'#tab-staff');
        $this->assertStringContainsString('href="'.$editUrl.'"', $html);
        $this->assertStringContainsString('>Edit</a>', $html);
    }

    public function test_saved_week_with_edit_flag_renders_editable_form(): void
    {
        $week = $this->weekStart(7);
        $this->saveRoster($week, [
            ['user_id' => $this->amStaff->id, 'start_time' => '10:00', 'end_time' => '16:00'],
        ]);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.availability', ['week' => $week, 'edit' => 1]))
            ->assertOk()
            ->getContent();

        // Editable, prefilled from the saved assignment.
        $this->assertStringContainsString('name="assignments[0][user_id]" value="'.$this->amStaff->id.'"', $html);
        $this->assertStringContainsString('name="assignments[0][start_time]" value="10:00"', $html);
        $this->assertStringContainsString('name="assignments[0][end_time]" value="16:00"', $html);
        $this->assertStringContainsString('+ Add staff', $html);
        $this->assertStringContainsString('Save Roster', $html);

        // Edit button is rendered in the header but disabled.
        $this->assertStringContainsString(self::DISABLED_EDIT, $html);

        // Cancel returns to the locked view of the same week (a link, no submit).
        $lockedUrl = route('admin.availability', ['week' => $week]).'#tab-staff';
        $this->assertStringContainsString('href="'.$lockedUrl.'">Cancel</a>', $html);
    }

    public function test_week_without_assignments_renders_setup_form(): void
    {
        $week = $this->weekStart(21);
        $this->assertFalse(StaffShiftAssignment::weekHasRoster($week));

        $html = $this->actingAs($this->manager)
            ->get(route('admin.availability', ['week' => $week]))
            ->assertOk()
            ->getContent();

        // Opens directly in the setup/edit form.
        $this->assertStringContainsString('name="week_start" value="'.$week.'"', $html);
        $this->assertStringContainsString('+ Add staff', $html);
        $this->assertStringContainsString('Save Roster', $html);

        // Edit button disabled; no Cancel because there is nothing to lock back to.
        $this->assertStringContainsString(self::DISABLED_EDIT, $html);
        $this->assertStringNotContainsString('>Cancel</a>', $html);
    }

    public function test_saving_roster_redirects_to_locked_view(): void
    {
        $week = $this->weekStart(7);

        $this->actingAs($this->manager)->post(route('admin.availability.weekly-roster'), [
            'week_start' => $week,
            'assignments' => [
                ['user_id' => $this->amStaff->id, 'start_time' => '09:00', 'end_time' => '15:00'],
            ],
        ])->assertRedirect(route('admin.availability', ['week' => $week]).'#tab-staff')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $html = $this->actingAs($this->manager)
            ->get(route('admin.availability', ['week' => $week]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<span class="roster-shift-time">9:00 AM</span>', $html);
        $this->assertStringContainsString('>Edit</a>', $html);
        $this->assertStringNotContainsString('name="assignments[', $html);
    }
}
