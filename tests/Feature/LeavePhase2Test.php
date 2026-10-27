<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\LeaveAdjustment;
use App\Models\LeaveRequest;
use App\Models\Service;
use App\Models\User;
use App\Support\LeaveBalances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Phase 2 of the leave work:
 *  A. Managers get 12 SL + 12 VL and rows in the admin Leaves tab (roster
 *     stays staff-only); only the owner reviews a manager's leave.
 *  B. The staff dashboard's "My Leaves" card shows the logged-in staff
 *     member's own balances only.
 *  C. Owner-only signed leave adjustments (separate rows, never an
 *     overwrite of the approved-days figure).
 *  D. Booking-conflict warning before filing, and on the approver's review.
 */
class LeavePhase2Test extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $owner;

    private User $manager;

    private User $staff;

    private User $client;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::where('email', 'admin@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->service = Service::where('status', 'active')->firstOrFail();
    }

    // ---- helpers ---------------------------------------------------------

    private function approvedLeave(User $user, string $type, string $start, string $end): LeaveRequest
    {
        return LeaveRequest::create([
            'user_id' => $user->id,
            'start_date' => $start,
            'end_date' => $end,
            'type' => $type,
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);
    }

    private function pendingLeave(User $user, string $type, string $start, string $end): LeaveRequest
    {
        return LeaveRequest::create([
            'user_id' => $user->id,
            'start_date' => $start,
            'end_date' => $end,
            'type' => $type,
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
    }

    private function bookAppointment(User $staffMember, string $date, string $time, AppointmentStatus $status): Appointment
    {
        return Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $staffMember->id,
            'appointment_date' => $date,
            'start_time' => $time,
            'status' => $status,
        ]);
    }

    /** Strip tags/whitespace so a row's cells read as one line of text. */
    private function normalize(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    }

    /** The <tr>…</tr> that contains $needle. */
    private function rowFor(string $html, string $needle): string
    {
        if (! preg_match('/<tr>(?:(?!<\/tr>).)*?'.preg_quote($needle, '/').'(?:(?!<\/tr>).)*?<\/tr>/s', $html, $match)) {
            $this->fail("No table row found containing \"{$needle}\".");
        }

        return $match[0];
    }

    /** Slice of the admin Employees page between the roster and leaves panes. */
    private function employeesPanes(string $html): array
    {
        $rosterPos = strpos($html, 'id="roster-tab-pane"');
        $leavesPos = strpos($html, 'id="leaves-tab-pane"');

        $this->assertNotFalse($rosterPos, 'Roster tab pane not found.');
        $this->assertNotFalse($leavesPos, 'Leaves tab pane not found.');

        return [
            substr($html, $rosterPos, $leavesPos - $rosterPos),
            substr($html, $leavesPos),
        ];
    }

    /** The conflict-warning block on the staff dashboard. */
    private function staffWarningBlock(string $html): string
    {
        $start = strpos($html, 'This leave overlaps');
        $end = strpos($html, 'Proceed anyway');

        $this->assertNotFalse($start, 'Conflict warning was not rendered.');
        $this->assertNotFalse($end, 'Conflict warning choices were not rendered.');

        return substr($html, $start, $end - $start);
    }

    // ---- A: managers in the Leaves tab, roster stays staff-only ----------

    public function test_leaves_tab_lists_managers_with_their_own_balances_while_roster_stays_staff_only(): void
    {
        $year = now()->year;

        // Manager consumes 3 SL days; staff stays fresh.
        $this->approvedLeave($this->manager, 'sick', "$year-02-02", "$year-02-04");

        $html = $this->actingAs($this->owner)->get(route('admin.employees'))->assertOk()->getContent();
        [$rosterHtml, $leavesHtml] = $this->employeesPanes($html);

        // Leaves tab: the manager row exists with their own 3 consumed / 9
        // remaining SL and their own untouched 0/12 VL.
        $this->assertStringContainsString('Leaves — '.$year, $leavesHtml);
        $managerRow = $this->normalize($this->rowFor($leavesHtml, 'Manager'));
        $this->assertStringContainsString('3 9 0 12', $managerRow);

        // Staff row still there with a full 12 + 12.
        $staffRow = $this->normalize($this->rowFor($leavesHtml, $this->staff->fullName()));
        $this->assertStringContainsString('0 12 0 12', $staffRow);

        // Roster & Attendance: staff only — the manager's email never appears
        // there, while the staff member's does.
        $this->assertStringContainsString($this->staff->email, $rosterHtml);
        $this->assertStringNotContainsString($this->manager->email, $rosterHtml);
    }

    // ---- A: only the owner reviews a manager's leave ----------------------

    public function test_a_manager_cannot_review_their_own_leave_request_but_the_owner_can(): void
    {
        $start = now()->addMonth()->startOfMonth()->toDateString();
        $leave = $this->pendingLeave($this->manager, 'vacation', $start, $start);

        // Server-side rejection, not just a hidden button.
        $this->actingAs($this->manager)
            ->patch(route('admin.leaves.review', $leave->id), ['status' => LeaveRequest::STATUS_APPROVED])
            ->assertSessionHasErrors('status');

        $this->assertSame(LeaveRequest::STATUS_PENDING, $leave->refresh()->status);
        $this->assertNull($leave->reviewed_by);

        // While it is pending, the manager's own review page offers no action
        // for it — the rule is enforced on the server above, not just here.
        $html = $this->actingAs($this->manager)->get(route('admin.leaves'))->assertOk()->getContent();
        $row = $this->rowFor($html, 'Manager');
        $this->assertStringContainsString('Owner review only', $row);
        $this->assertStringNotContainsString('Approve', $row);

        // The owner can.
        $this->actingAs($this->owner)
            ->patch(route('admin.leaves.review', $leave->id), ['status' => LeaveRequest::STATUS_APPROVED])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leave->status);
        $this->assertSame($this->owner->id, $leave->reviewed_by);
    }

    // ---- B: My Leaves card is the logged-in user's own numbers only -------

    public function test_staff_dashboard_my_leaves_card_shows_only_the_logged_in_staffs_numbers(): void
    {
        $year = now()->year;

        // The logged-in staff member: 3 SL days approved.
        $this->approvedLeave($this->staff, 'sick', "$year-03-02", "$year-03-04");

        // A different staff member with very different numbers.
        $other = User::create([
            'name' => 'Zara Other',
            'email' => 'zara.other@nexflio.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_STAFF,
            'email_verified_at' => now(),
        ]);
        $this->approvedLeave($other, 'sick', "$year-04-01", "$year-04-05");

        $html = $this->actingAs($this->staff)->get(route('staff.dashboard'))->assertOk()->getContent();
        $text = $this->normalize($html);

        $this->assertStringContainsString('My Leaves — '.$year, $text);
        $this->assertStringContainsString('Sick Leave 3 9 Vacation Leave 0 12', $text);

        // The other employee's row must not leak into this card.
        $this->assertStringNotContainsString('Sick Leave 5 7', $text);
        $this->assertStringNotContainsString('Zara Other', $text);
    }

    // ---- C: adjustment math ----------------------------------------------

    public function test_owner_adjustment_math_reduces_adds_back_and_floors_at_zero(): void
    {
        $year = now()->year;

        // 3 approved SL days consumed -> 9 remaining.
        $this->approvedLeave($this->staff, 'sick', "$year-05-04", "$year-05-06");
        $this->assertEquals(3, LeaveBalances::forUser($this->staff, $year)['sl_used']);
        $this->assertEquals(9, LeaveBalances::forUser($this->staff, $year)['sl_remaining']);

        // -1 correction -> 8 remaining; the consumed figure is untouched.
        $this->actingAs($this->owner)
            ->patch(route('admin.employees.leave-adjustment', $this->staff->id), [
                'sl_days' => -1,
                'reason' => 'One day was keyed against the wrong employee.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $balances = LeaveBalances::forUser($this->staff, $year);
        $this->assertEquals(8, $balances['sl_remaining']);
        $this->assertEquals(3, $balances['sl_used']);

        // +2 on fresh balances -> 14, deliberately NOT capped back to 12.
        $this->actingAs($this->owner)
            ->patch(route('admin.employees.leave-adjustment', $this->manager->id), [
                'sl_days' => 2,
                'reason' => 'Carry-over agreed with the owner.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(14, LeaveBalances::forUser($this->manager, $year)['sl_remaining']);

        // The only floor is zero.
        $this->actingAs($this->owner)
            ->patch(route('admin.employees.leave-adjustment', $this->manager->id), [
                'vl_days' => -50,
                'reason' => 'Over-advanced vacation leave.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(0, LeaveBalances::forUser($this->manager, $year)['vl_remaining']);

        // Each correction is its own auditable row (3 rows: -1, +2, -50).
        $this->assertSame(3, LeaveAdjustment::count());
        $this->assertSame($this->owner->id, LeaveAdjustment::firstOrFail()->created_by);
    }

    // ---- C: reason required + owner only ---------------------------------

    public function test_adjustment_requires_a_reason_and_is_owner_only(): void
    {
        // Missing reason -> validation error, nothing written.
        $this->actingAs($this->owner)
            ->patch(route('admin.employees.leave-adjustment', $this->staff->id), ['sl_days' => -1])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, LeaveAdjustment::count());

        // A manager is refused server-side (route is owner-only) and cannot
        // write an adjustment even with a valid payload.
        $this->actingAs($this->manager)
            ->patch(route('admin.employees.leave-adjustment', $this->staff->id), [
                'sl_days' => -1,
                'reason' => 'Trying to adjust as a manager.',
            ])
            ->assertForbidden();

        $this->assertSame(0, LeaveAdjustment::count());

        // Managers also never see the Edit action or its modal.
        $html = $this->actingAs($this->manager)->get(route('admin.employees'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="leaveAdjustmentModal"', $html);
        $this->assertStringNotContainsString('/leave-adjustment', $html);
    }

    // ---- C: adjustments visible in both places ---------------------------

    public function test_adjustment_is_reflected_in_the_leaves_tab_and_the_my_leaves_card(): void
    {
        $year = now()->year;

        $this->actingAs($this->owner)
            ->patch(route('admin.employees.leave-adjustment', $this->staff->id), [
                'sl_days' => -2,
                'reason' => 'Two days deducted after a late filing.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Admin Leaves tab: 0 consumed, 10 remaining.
        $html = $this->actingAs($this->owner)->get(route('admin.employees'))->assertOk()->getContent();
        [, $leavesHtml] = $this->employeesPanes($html);
        $staffRow = $this->normalize($this->rowFor($leavesHtml, $this->staff->fullName()));
        $this->assertStringContainsString('0 10 0 12', $staffRow);

        // Staff dashboard card: same numbers.
        $dashboard = $this->actingAs($this->staff)->get(route('staff.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString(
            'Sick Leave 0 10 Vacation Leave 0 12',
            $this->normalize($dashboard)
        );
    }

    // ---- D: booking-conflict warning before filing ------------------------

    public function test_conflict_warning_lists_the_booking_and_only_files_after_confirmation(): void
    {
        $date = now()->addWeek()->toDateString();
        $this->bookAppointment($this->staff, $date, '10:00:00', AppointmentStatus::Booked);

        $payload = [
            'start_date' => $date,
            'end_date' => $date,
            'type' => 'vacation',
            'reason' => 'Family trip.',
        ];

        // First submit: warning, nothing filed.
        $this->actingAs($this->staff)
            ->post(route('staff.leaves.store'), $payload)
            ->assertRedirect(route('staff.dashboard'))
            ->assertSessionHas('leaveConflicts');

        $this->assertSame(0, LeaveRequest::count());

        // The re-rendered form lists the conflicting appointment with both
        // choices, and keeps the typed values.
        $html = $this->actingAs($this->staff)->get(route('staff.dashboard'))->assertOk()->getContent();
        $warning = $this->staffWarningBlock($html);

        $this->assertStringContainsString(Carbon::parse($date)->format('M j, Y'), $warning);
        $this->assertStringContainsString('10:00 AM', $warning);
        $this->assertStringContainsString($this->service->name, $warning);
        $this->assertStringContainsString($this->client->name, $warning);
        $this->assertStringContainsString('Change dates', $html);
        $this->assertStringContainsString('value="'.$date.'"', $html);
        $this->assertStringContainsString('Family trip.', $html);

        // "Proceed anyway" files it.
        $this->actingAs($this->staff)
            ->post(route('staff.leaves.store'), $payload + ['confirm_conflicts' => '1'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, LeaveRequest::count());
        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $this->staff->id,
            'type' => 'vacation',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
    }

    public function test_no_conflict_files_immediately_and_cancelled_or_completed_bookings_do_not_warn(): void
    {
        // A clean week: no appointments at all.
        $clean = now()->addWeeks(2)->toDateString();
        $this->actingAs($this->staff)
            ->post(route('staff.leaves.store'), [
                'start_date' => $clean,
                'end_date' => $clean,
                'type' => 'sick',
                'reason' => 'Flu.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('leaveConflicts');

        $this->assertSame(1, LeaveRequest::count());

        // Cancelled and Completed bookings are not conflicts.
        $cancelledDate = now()->addWeeks(3)->toDateString();
        $this->bookAppointment($this->staff, $cancelledDate, '11:00:00', AppointmentStatus::Cancelled);
        $this->bookAppointment($this->staff, $cancelledDate, '13:00:00', AppointmentStatus::Completed);

        $this->actingAs($this->staff)
            ->post(route('staff.leaves.store'), [
                'start_date' => $cancelledDate,
                'end_date' => $cancelledDate,
                'type' => 'vacation',
                'reason' => 'Long weekend.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('leaveConflicts');

        $this->assertSame(2, LeaveRequest::count());
    }

    // ---- D: the approver sees the same conflict list ----------------------

    public function test_admin_leave_review_shows_the_conflicting_bookings(): void
    {
        $date = now()->addWeeks(4)->toDateString();
        $leave = $this->pendingLeave($this->staff, 'vacation', $date, $date);
        $this->bookAppointment($this->staff, $date, '14:30:00', AppointmentStatus::Booked);

        $html = $this->actingAs($this->owner)->get(route('admin.leaves'))->assertOk()->getContent();

        $this->assertStringContainsString('Overlapping bookings', $html);

        $start = strpos($html, 'Overlapping bookings');
        $block = substr($html, $start, 800);

        $this->assertStringContainsString(Carbon::parse($date)->format('M j, Y'), $block);
        $this->assertStringContainsString('2:30 PM', $block);
        $this->assertStringContainsString($this->service->name, $block);
        $this->assertStringContainsString($this->client->name, $block);

        // Display-only: the request is still pending and reviewable.
        $this->assertSame(LeaveRequest::STATUS_PENDING, $leave->refresh()->status);
        $this->assertStringContainsString('Approve', $html);
    }
}
