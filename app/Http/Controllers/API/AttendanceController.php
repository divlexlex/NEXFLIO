<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function timeIn(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        $exists = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You have already timed in today.'], 409);
        }

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => $today,
            'time_in' => now(),
        ]);

        return response()->json([
            'message' => 'Timed in.',
            'attendance' => $attendance,
        ], 201);
    }

    public function timeOut(Request $request)
    {
        $attendance = Attendance::where('user_id', $request->user()->id)
            ->whereDate('work_date', now()->toDateString())
            ->whereNull('time_out')
            ->first();

        if (! $attendance) {
            return response()->json(['message' => 'No open attendance to time out from.'], 409);
        }

        $attendance->update(['time_out' => now()]);

        return response()->json([
            'message' => 'Timed out.',
            'attendance' => $attendance,
        ]);
    }

    public function mine(Request $request)
    {
        $month = $request->query('month', now()->format('Y-m'));
        [$year, $mon] = explode('-', $month) + [now()->year, now()->month];

        return Attendance::where('user_id', $request->user()->id)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $mon)
            ->orderByDesc('work_date')
            ->get();
    }

    public function index(Request $request)
    {
        $date = $request->query('date', now()->toDateString());

        return Attendance::with('user:id,name')
            ->whereDate('work_date', $date)
            ->orderBy('time_in')
            ->get();
    }
}
