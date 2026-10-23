<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveRequest;
use App\Models\Attendance;
use App\Models\Commission;
use App\Models\LeaveRequest;
use App\Models\StaffShiftAssignment;
use App\Services\AppointmentService;
use App\Services\WeeklyPayrollService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Self-service Staff (role 3) portal — one dashboard page covering the same
 * ground the mobile app's equivalent API endpoints already cover
 * (App\Http\Controllers\API\AttendanceController / LeaveController): clock
 * in/out, today's + upcoming assigned appointments, this week's
 * pay and commissions, and their own leave request history/submission. Admin/Manager
 * review of leave requests is unchanged (App\Http\Controllers\Web\LeaveController).
 */
class StaffController extends Controller
{
    public function dashboard(Request $request, WeeklyPayrollService $payroll)
    {
        $user = $request->user();

        // This week's pay (Sun–Sat): daily base pay × days attended +
        // this week's commissions — same WeeklyPayrollService the admin
        // Billing page and the pay API use.
        [$weekStart, $weekEnd] = $payroll->weekRange(now()->toDateString());
        $weeklyPay = $payroll->forStaff($user, $weekStart);
        $weeklyPay['week_start'] = $weekStart;
        $weeklyPay['week_end'] = $weekEnd;
        $today = now()->toDateString();

        $todaysAppointments = $user->assignedAppointments()
            ->whereDate('appointment_date', $today)
            ->with(['service:id,name', 'user:id,name'])
            ->orderBy('start_time')
            ->get();

        $upcomingAppointments = $user->assignedAppointments()
            ->whereDate('appointment_date', '>', $today)
            ->whereNotIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::NoShow])
            ->with(['service:id,name', 'user:id,name'])
            ->orderBy('appointment_date')->orderBy('start_time')
            ->take(10)
            ->get();

        return view('staff.dashboard', [
            'todayAttendance' => Attendance::where('user_id', $user->id)->whereDate('work_date', $today)->first(),
            'todaysAppointments' => $todaysAppointments,
            'upcomingAppointments' => $upcomingAppointments,
            'weeklyPay' => $weeklyPay,
            'recentCommissions' => Commission::where('user_id', $user->id)
                ->with('appointment.service:id,name')
                ->latest('earned_at')
                ->take(10)
                ->get(),
            'leaves' => LeaveRequest::where('user_id', $user->id)->latest()->take(10)->get(),
        ]);
    }

    /**
     * "My Attendance" — the logged-in staff member's OWN attendance history
     * for one month (defaults to the current month), plus a monthly summary.
     * Read-only: the clock controls reuse the same POST endpoints as the
     * dashboard. The month is taken from ?month=YYYY-MM with any invalid
     * value silently falling back to the current month (never a 500).
     */
    public function attendance(Request $request)
    {
        $user = $request->user();

        $monthParam = $request->query('month');
        $start = now()->startOfMonth();

        if (is_string($monthParam) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParam)) {
            try {
                $start = Carbon::createFromFormat('Y-m-d', $monthParam.'-01')->startOfMonth();
            } catch (\Throwable $e) {
                $start = now()->startOfMonth();
            }
        }

        $end = $start->copy()->endOfMonth();
        $isCurrentMonth = $start->isSameMonth(now());

        // Only this staff member's rows, keyed by day for the history table.
        $rows = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->orderByDesc('work_date')
            ->get()
            ->keyBy(fn ($row) => $row->work_date->toDateString());

        $totalMinutes = (int) $rows->sum(fn ($row) => $row->workedMinutes() ?? 0);

        // Every calendar day from the 1st through today (current month) or
        // month-end (past months) — never future days, newest first.
        $lastDay = $isCurrentMonth ? now()->startOfDay() : $end->copy()->startOfDay();
        $days = [];
        for ($day = $start->copy(); $day->lte($lastDay); $day->addDay()) {
            $days[] = $day->copy();
        }
        $days = array_reverse($days);

        return view('staff.attendance', [
            'todayAttendance' => Attendance::where('user_id', $user->id)
                ->whereDate('work_date', now()->toDateString())
                ->first(),
            'monthStart' => $start,
            'isCurrentMonth' => $isCurrentMonth,
            'prevMonth' => $start->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $start->copy()->addMonth()->format('Y-m'),
            'daysPresent' => $rows->filter(fn ($row) => $row->time_in !== null)->count(),
            'totalHours' => $this->humanWorkedTime($totalMinutes),
            'lateCount' => $rows->filter(fn ($row) => $row->isLate())->count(),
            'days' => $days,
            'rows' => $rows,
        ]);
    }

    /**
     * Format a minute total with the Attendance model's own formatter so the
     * monthly summary uses exactly the same "8h 30m"/"45m" shape as the rows
     * (the model's #8 helpers are final and must not be modified).
     */
    private function humanWorkedTime(int $minutes): string
    {
        return (new Attendance)->forceFill([
            'time_in' => Carbon::create(2000, 1, 1, 0, 0, 0),
            'time_out' => Carbon::create(2000, 1, 1, 0, 0, 0)->addMinutes($minutes),
        ])->workedForHumans();
    }

    /**
     * Personal schedule page — read-only FullCalendar of the logged-in
     * staff member's own appointments and Weekly Roster shifts.
     */
    public function schedule(Request $request)
    {
        return view('staff.schedule', [
            'staff' => $request->user()->loadMissing('staffProfile'),
        ]);
    }

    /**
     * FullCalendar JSON feed for the schedule page. Scoped to the logged-in
     * staff member only — no user id is ever accepted from the request.
     * Cancelled / No-Show appointments are excluded (unlike the admin feed,
     * which keeps them visible greyed out).
     */
    public function scheduleEvents(Request $request)
    {
        $user = $request->user();

        $rangeStart = $request->query('start')
            ? Carbon::parse($request->query('start'))->startOfDay()
            : now()->startOfMonth();
        $rangeEnd = $request->query('end')
            ? Carbon::parse($request->query('end'))->startOfDay()
            : now()->copy()->endOfMonth()->addDay()->startOfDay();

        $events = [];

        // ── (a) Own assigned appointments in range ────────────────────
        $appointments = $user->assignedAppointments()
            ->with(['service:id,name,duration_minutes', 'user:id,name'])
            ->whereDate('appointment_date', '>=', $rangeStart->toDateString())
            ->whereDate('appointment_date', '<', $rangeEnd->toDateString())
            ->whereNotIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::NoShow])
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get();

        foreach ($appointments as $appointment) {
            $date = $appointment->appointment_date->toDateString();
            $start = Carbon::parse("{$date}T{$appointment->start_time}");
            $duration = $appointment->service->duration_minutes ?? 60;
            $end = $start->copy()->addMinutes($duration);

            $statusKey = $appointment->status->value;
            [$bg, $border, $fg] = match ($statusKey) {
                'unverified' => ['#fcebd2', '#ecc78f', '#8a5a16'],
                'booked' => ['#dcf0e2', '#a9d9bc', '#1f6b41'],
                'in-service' => ['#deeafe', '#aec8f7', '#1b4fc0'],
                'completed' => ['#eae1f6', '#cdb8ec', '#5e3a97'],
                'cancelled' => ['#ececec', '#d9d9d9', '#7a7a7a'],
                'no-show' => ['#f6e5e3', '#e4c0ba', '#93534a'],
                default => ['#e9e2d8', '#d8ccbc', '#5b5148'],
            };

            $events[] = [
                'id' => $appointment->id,
                'title' => $appointment->referenceCode().' · '.($appointment->clientName() ?? 'Client').' · '.$appointment->displayServiceName(),
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'backgroundColor' => $bg,
                'borderColor' => $border,
                'textColor' => $fg,
                'classNames' => ['nx-evt', 'nx-evt-'.$statusKey],
                'extendedProps' => [
                    'kind' => 'appointment',
                    'client' => $appointment->clientName() ?? 'Client',
                    'reference_code' => $appointment->referenceCode(),
                    'service' => $appointment->displayServiceName(),
                    'status' => $statusKey,
                    'statusLabel' => $appointment->status->label(),
                    'dateLabel' => $appointment->appointment_date->format('M j, Y (D)'),
                    'timeLabel' => $start->format('g:i A').' – '.$end->format('g:i A'),
                ],
            ];
        }

        // ── (b) Own Weekly Roster shifts: one background event per day ──
        $weekStarts = [];
        $cursor = Carbon::parse(StaffShiftAssignment::weekStartOf($rangeStart->toDateString()));
        $lastWeek = StaffShiftAssignment::weekStartOf($rangeEnd->copy()->subDay()->toDateString());
        while ($cursor->toDateString() <= $lastWeek) {
            $weekStarts[] = $cursor->toDateString();
            $cursor->addWeek();
        }

        $assignments = StaffShiftAssignment::where('user_id', $user->id)
            ->whereIn('week_start', $weekStarts)
            ->get()
            ->keyBy(fn ($assignment) => Carbon::parse($assignment->week_start)->toDateString());

        foreach ($weekStarts as $weekStart) {
            $assignment = $assignments->get($weekStart);

            if (! $assignment) {
                continue;
            }

            $shiftLabel = Carbon::parse($assignment->start_time)->format('g:i A')
                .' – '.Carbon::parse($assignment->end_time)->format('g:i A');

            for ($i = 0; $i < 7; $i++) {
                $day = Carbon::parse($weekStart)->addDays($i)->toDateString();

                if ($day < $rangeStart->toDateString() || $day >= $rangeEnd->toDateString()) {
                    continue;
                }

                $events[] = [
                    'id' => 'shift-'.$assignment->id.'-'.$day,
                    'title' => $shiftLabel,
                    'start' => $day.'T'.$assignment->start_time,
                    'end' => $day.'T'.$assignment->end_time,
                    'display' => 'background',
                    'backgroundColor' => 'rgba(201, 162, 75, 0.20)',
                    'extendedProps' => ['kind' => 'shift'],
                ];
            }
        }

        // ── (c) Own approved leave overlapping the range ──────────────
        $leaves = LeaveRequest::where('user_id', $user->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('end_date', '>=', $rangeStart->toDateString())
            ->whereDate('start_date', '<', $rangeEnd->toDateString())
            ->get();

        foreach ($leaves as $leave) {
            $events[] = [
                'id' => 'leave-'.$leave->id,
                'title' => 'On leave',
                'start' => $leave->start_date->toDateString(),
                'end' => $leave->end_date->copy()->addDay()->toDateString(),
                'allDay' => true,
                'backgroundColor' => '#ececec',
                'borderColor' => '#d9d9d9',
                'textColor' => '#7a7a7a',
                'classNames' => ['nx-evt', 'nx-evt-leave'],
                'extendedProps' => ['kind' => 'leave'],
            ];
        }

        return response()->json($events);
    }

    public function timeIn(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        if (Attendance::where('user_id', $user->id)->whereDate('work_date', $today)->exists()) {
            return back()->withErrors(['attendance' => 'You have already timed in today.']);
        }

        Attendance::create(['user_id' => $user->id, 'work_date' => $today, 'time_in' => now()]);

        return back()->with('success', 'Timed in — have a great shift!');
    }

    public function timeOut(Request $request)
    {
        $attendance = Attendance::where('user_id', $request->user()->id)
            ->whereDate('work_date', now()->toDateString())
            ->whereNull('time_out')
            ->first();

        if (! $attendance) {
            return back()->withErrors(['attendance' => 'No open attendance to time out from.']);
        }

        $attendance->update(['time_out' => now()]);

        // A wrap-up clears any break state so the next shift starts fresh.
        $profile = $request->user()->staffProfile;
        if ($profile) {
            $profile->update(['is_on_break' => false, 'break_started_at' => null]);
        }

        return back()->with('success', 'Timed out. See you next shift!');
    }

    /**
     * Toggle the staff member's break state from the web dashboard. Only
     * allowed while they have an open attendance for today; mirrors the
     * mobile API toggle (App\Http\Controllers\API\StaffController) so the
     * booking pool exclusion stays driven by the same profile flag.
     */
    public function toggleBreak(Request $request)
    {
        $user = $request->user();

        $clockedIn = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', now()->toDateString())
            ->whereNull('time_out')
            ->exists();

        $profile = $user->staffProfile;

        if (! $clockedIn || ! $profile) {
            return back()->withErrors([
                'attendance' => 'You can only change break status while timed in.',
            ]);
        }

        $target = ! $profile->is_on_break;

        $profile->update([
            'is_on_break' => $target,
            'break_started_at' => $target ? now() : null,
        ]);

        return back()->with('success', $target
            ? 'You are now on break.'
            : 'You are back in the booking pool.');
    }

    public function updatePassword(Request $request)
    {
        // Every Staff account starts on an Admin-generated temporary
        // password (see EmployeeController::store()) — this is how they
        // change it to something only they know.
        $validated = $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $request->user()->update(['password' => Hash::make($validated['password'])]);

        return back()->with('success', 'Password updated.');
    }

    /**
     * Staff marks their own in-service appointment as completed.
     * Only allowed for appointments assigned to the logged-in staff
     * that are currently in-service.
     */
    public function completeService(Request $request, AppointmentService $appointmentService, $id)
    {
        $user = $request->user();

        $appointment = $user->assignedAppointments()
            ->where('id', $id)
            ->where('status', AppointmentStatus::InService)
            ->firstOrFail();

        $appointmentService->transition(
            $appointment,
            AppointmentStatus::Completed,
            $user->id
        );

        return back()->with('success', 'Service marked as completed.');
    }

    public function storeLeave(StoreLeaveRequest $request)
    {
        LeaveRequest::create([
            'user_id' => $request->user()->id,
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'type' => $request->input('type'),
            'reason' => $request->input('reason'),
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        return back()->with('success', 'Leave request submitted.');
    }
}
