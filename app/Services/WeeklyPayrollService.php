<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Commission;
use App\Models\StaffShiftAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Weekly payroll (sha, 2026-10-24) — the single source of truth for
 * employee pay, used by Admin Billing, the staff pay API and the staff
 * dashboard so the three surfaces can never disagree:
 *
 *   Weekly Pay = Daily Base Pay × Days Attended That Week
 *              + Commissions Earned That Week
 *
 * Weeks run Sunday–Saturday, the same week system as the Weekly Roster
 * (StaffShiftAssignment::weekStartOf). Days attended = attendance rows in
 * the week with a time_in (the attendances table is unique per user/day,
 * so one row = one day; a late day still pays the full daily rate and an
 * open shift counts — no hourly proration, no deductions). Commissions
 * are the immutable ledger rows whose earned_at falls in the same week.
 * The old fixed monthly base_pay is no longer part of pay.
 */
class WeeklyPayrollService
{
    /**
     * The Sunday (week start) and Saturday (week end) of the week
     * containing the given date, as Y-m-d strings.
     *
     * @return array{0: string, 1: string}
     */
    public function weekRange(string $anyDate): array
    {
        $weekStart = StaffShiftAssignment::weekStartOf($anyDate);
        $weekEnd = Carbon::parse($weekStart)->addDays(6)->toDateString();

        return [$weekStart, $weekEnd];
    }

    /**
     * One staff member's pay row for the week starting on $weekStart
     * (a Sunday, Y-m-d).
     *
     * @return array{user: User, daily_base_pay: float, days_present: int, base_total: float, commission: float, total: float}
     */
    public function forStaff(User $staff, string $weekStart): array
    {
        [$start, $end] = $this->weekRange($weekStart);

        // whereDate (not whereBetween on the raw column): work_date can be
        // stored with a 00:00:00 time part, which would string-compare
        // AFTER the bare end date and drop the Saturday boundary day.
        $daysPresent = Attendance::where('user_id', $staff->id)
            ->whereDate('work_date', '>=', $start)
            ->whereDate('work_date', '<=', $end)
            ->whereNotNull('time_in')
            ->count();

        $commission = (float) Commission::where('user_id', $staff->id)
            ->whereBetween('earned_at', [$start.' 00:00:00', $end.' 23:59:59'])
            ->sum('amount');

        $dailyBasePay = (float) ($staff->staffProfile?->daily_base_pay ?? 0);
        $baseTotal = round($dailyBasePay * $daysPresent, 2);
        $commission = round($commission, 2);

        return [
            'user' => $staff,
            'daily_base_pay' => $dailyBasePay,
            'days_present' => $daysPresent,
            'base_total' => $baseTotal,
            'commission' => $commission,
            'total' => round($baseTotal + $commission, 2),
        ];
    }

    /**
     * Pay rows for every staff member for the week starting on
     * $weekStart (a Sunday, Y-m-d), ordered by name.
     */
    public function forAllStaff(string $weekStart): Collection
    {
        return User::where('role_id', User::ROLE_STAFF)
            ->with('staffProfile')
            ->orderBy('name')
            ->get()
            ->map(fn (User $member) => $this->forStaff($member, $weekStart));
    }
}
