<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveRequest;
use App\Models\Attendance;
use App\Models\Commission;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Self-service Staff (role 3) portal — one dashboard page covering the same
 * ground the mobile app's equivalent API endpoints already cover
 * (App\Http\Controllers\API\AttendanceController / LeaveController): clock
 * in/out, today's + upcoming assigned appointments, this month's
 * commissions, and their own leave request history/submission. Admin/Manager
 * review of leave requests is unchanged (App\Http\Controllers\Web\LeaveController).
 */
class StaffController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
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
            'commissionsThisMonth' => Commission::where('user_id', $user->id)
                ->whereYear('earned_at', now()->year)
                ->whereMonth('earned_at', now()->month)
                ->sum('amount'),
            'recentCommissions' => Commission::where('user_id', $user->id)
                ->with('appointment.service:id,name')
                ->latest('earned_at')
                ->take(10)
                ->get(),
            'leaves' => LeaveRequest::where('user_id', $user->id)->latest()->take(10)->get(),
        ]);
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

        return back()->with('success', 'Timed out. See you next shift!');
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
