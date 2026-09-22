<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlockedSlot;
use App\Models\BusinessHours;
use App\Models\Service;
use App\Models\StaffSchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AvailabilityController extends Controller
{
    public function index()
    {
        $businessHours = BusinessHours::orderBy('day_of_week')->get();
        $staff = User::where('role_id', User::ROLE_STAFF)
            ->with('staffProfile')
            ->orderBy('last_name')
            ->get();
        $schedules = StaffSchedule::orderBy('user_id')->orderBy('day_of_week')->get();
        $services = Service::where('status', 'active')->orderBy('name')->get();
        $blockedSlots = BlockedSlot::with('personnel', 'creator')
            ->where('blocked_date', '>=', now()->toDateString())
            ->orderBy('blocked_date')
            ->orderBy('start_time')
            ->get();

        return view('admin.availability.index', compact(
            'businessHours', 'staff', 'schedules', 'services', 'blockedSlots'
        ));
    }

    // ─── Business Hours ───────────────────────────────────────────────

    public function updateBusinessHours(Request $request)
    {
        $validated = $request->validate([
            'hours'   => 'required|array|size:7',
            'hours.*.is_open'   => 'required|boolean',
            'hours.*.open_at'   => 'required_if:hours.*.is_open,1|nullable|date_format:H:i',
            'hours.*.close_at'  => 'required_if:hours.*.is_open,1|nullable|date_format:H:i|after:hours.*.open_at',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['hours'] as $day => $data) {
                BusinessHours::updateOrCreate(
                    ['day_of_week' => $day],
                    [
                        'is_open'  => $data['is_open'] ?? false,
                        'open_at'  => $data['open_at'] ?? '10:00:00',
                        'close_at' => $data['close_at'] ?? '21:00:00',
                    ]
                );
            }
        });

        return back()->with('success', 'Business hours updated.');
    }

    // ─── Staff Schedules ──────────────────────────────────────────────

    public function updateStaffSchedule(Request $request)
    {
        $validated = $request->validate([
            'user_id'  => ['required', 'exists:users,id'],
            'schedule' => 'required|array|size:7',
            'schedule.*.is_available' => 'required|boolean',
            'schedule.*.start_time'   => 'required_if:schedule.*.is_available,1|nullable|date_format:H:i',
            'schedule.*.end_time'     => 'required_if:schedule.*.is_available,1|nullable|date_format:H:i|after:schedule.*.start_time',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['schedule'] as $day => $data) {
                StaffSchedule::updateOrCreate(
                    ['user_id' => $validated['user_id'], 'day_of_week' => $day],
                    [
                        'is_available' => $data['is_available'] ?? false,
                        'start_time'   => $data['start_time'] ?? '10:00:00',
                        'end_time'     => $data['end_time'] ?? '21:00:00',
                    ]
                );
            }
        });

        return back()->with('success', 'Staff schedule updated.');
    }

    // ─── Service Time Restrictions ────────────────────────────────────

    public function updateServiceHours(Request $request)
    {
        $validated = $request->validate([
            'service_id'      => ['required', 'exists:services,id'],
            'available_from'  => 'nullable|date_format:H:i',
            'available_until' => 'nullable|date_format:H:i|after:available_from',
        ]);

        Service::where('id', $validated['service_id'])->update([
            'available_from'  => $validated['available_from'] ?? null,
            'available_until' => $validated['available_until'] ?? null,
        ]);

        return back()->with('success', 'Service hours updated.');
    }

    // ─── Blocked Slots ────────────────────────────────────────────────

    public function storeBlockedSlot(Request $request)
    {
        $validated = $request->validate([
            'blocked_date'  => 'required|date|after_or_equal:today',
            'start_time'    => 'nullable|date_format:H:i',
            'end_time'      => 'nullable|date_format:H:i|after:start_time',
            'personnel_id'  => 'nullable|exists:users,id',
            'reason'        => 'nullable|string|max:255',
        ]);

        BlockedSlot::create([
            'blocked_date'  => $validated['blocked_date'],
            'start_time'    => $validated['start_time'] ?? null,
            'end_time'      => $validated['end_time'] ?? null,
            'personnel_id'  => $validated['personnel_id'] ?? null,
            'reason'        => $validated['reason'] ?? null,
            'created_by'    => auth()->id(),
        ]);

        return back()->with('success', 'Slot blocked.');
    }

    public function destroyBlockedSlot($id)
    {
        BlockedSlot::findOrFail($id)->delete();

        return back()->with('success', 'Blocked slot removed.');
    }
}
