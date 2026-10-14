<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlockedSlot;
use App\Models\BusinessHours;
use App\Models\Service;
use App\Models\StaffSchedule;
use App\Models\StaffShiftAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AvailabilityController extends Controller
{
    public function index(Request $request)
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

        // ── Weekly Roster week plumbing ──────────────────────────────
        // ?week accepts any date inside the desired week; it is normalized
        // to that week's Sunday. ?copy_from (a previous week's Sunday, or
        // any date inside it) prefills checkboxes/times from that week while
        // Save still writes to ?week's Sunday.
        $weekStart = $this->normalizeWeekParam($request->query('week'))
            ?? StaffShiftAssignment::weekStartOf(now()->toDateString());

        $copyFrom = $this->normalizeWeekParam($request->query('copy_from'));
        $sourceWeek = $copyFrom ?? $weekStart;

        $sourceAssignments = StaffShiftAssignment::forWeek($sourceWeek)->get();
        $amRows = $sourceAssignments->where('shift', 'am')->values();
        $pmRows = $sourceAssignments->where('shift', 'pm')->values();

        $rosterWeekStart = $weekStart;
        $rosterWeekEnd = \Carbon\Carbon::parse($weekStart)->addDays(6)->toDateString();
        $rosterPrevWeek = \Carbon\Carbon::parse($weekStart)->subDays(7)->toDateString();
        $rosterNextWeek = \Carbon\Carbon::parse($weekStart)->addDays(7)->toDateString();
        $rosterWeekLabel = 'Week of '.\Carbon\Carbon::parse($weekStart)->format('M j')
            .' – '.\Carbon\Carbon::parse($rosterWeekEnd)->format('j, Y');
        $rosterAmStaff = $amRows->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $rosterPmStaff = $pmRows->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $rosterAmStart = $amRows->first()
            ? \Carbon\Carbon::parse($amRows->first()->start_time)->format('H:i') : '10:00';
        $rosterAmEnd = $amRows->first()
            ? \Carbon\Carbon::parse($amRows->first()->end_time)->format('H:i') : '16:00';
        $rosterPmStart = $pmRows->first()
            ? \Carbon\Carbon::parse($pmRows->first()->start_time)->format('H:i') : '16:00';
        $rosterPmEnd = $pmRows->first()
            ? \Carbon\Carbon::parse($pmRows->first()->end_time)->format('H:i') : '21:00';

        return view('admin.availability.index', compact(
            'businessHours', 'staff', 'schedules', 'services', 'blockedSlots',
            'rosterWeekStart', 'rosterWeekEnd', 'rosterPrevWeek', 'rosterNextWeek',
            'rosterWeekLabel', 'rosterAmStaff', 'rosterPmStaff',
            'rosterAmStart', 'rosterAmEnd', 'rosterPmStart', 'rosterPmEnd'
        ));
    }

    /**
     * Normalize a ?week / ?copy_from value to its Sunday (Y-m-d), or null
     * when absent or unparseable (the caller falls back to the current week).
     */
    private function normalizeWeekParam($value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return StaffShiftAssignment::weekStartOf(trim($value));
        } catch (\Throwable) {
            return null;
        }
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

    // ─── Weekly Roster (AM/PM shifts) ──────────────────────────────────

    /**
     * Save one week's AM/PM roster: existing assignments for that week are
     * replaced wholesale in a single transaction. week_start accepts any
     * date and is normalized to its Sunday. A person works one shift per
     * week — appearing in both lists is a validation error naming them.
     */
    public function updateWeeklyRoster(Request $request)
    {
        $validated = $request->validate([
            'week_start' => 'required|date',
            'am_start' => 'required|date_format:H:i',
            'am_end' => 'required|date_format:H:i|after:am_start',
            'pm_start' => 'required|date_format:H:i',
            'pm_end' => 'required|date_format:H:i|after:pm_start',
            'am_staff' => 'nullable|array',
            'am_staff.*' => [
                'integer',
                Rule::exists('users', 'id')->where('role_id', User::ROLE_STAFF)->whereNull('deleted_at'),
            ],
            'pm_staff' => 'nullable|array',
            'pm_staff.*' => [
                'integer',
                Rule::exists('users', 'id')->where('role_id', User::ROLE_STAFF)->whereNull('deleted_at'),
            ],
        ]);

        $amStaff = collect($validated['am_staff'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $pmStaff = collect($validated['pm_staff'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        $overlap = $amStaff->intersect($pmStaff);
        if ($overlap->isNotEmpty()) {
            $names = User::whereIn('id', $overlap->all())->orderBy('name')->pluck('name')->all();

            throw ValidationException::withMessages([
                'pm_staff' => 'One shift per person per week: '.implode(', ', $names).' cannot be on both AM and PM.',
            ]);
        }

        $inactive = User::whereIn('id', $amStaff->merge($pmStaff)->all())
            ->whereDoesntHave('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
            ->orderBy('name')
            ->pluck('name')
            ->all();
        if ($inactive !== []) {
            throw ValidationException::withMessages([
                'am_staff' => 'Only active staff can be rostered: '.implode(', ', $inactive).'.',
            ]);
        }

        $weekStart = StaffShiftAssignment::weekStartOf($validated['week_start']);

        DB::transaction(function () use ($weekStart, $validated, $amStaff, $pmStaff) {
            StaffShiftAssignment::forWeek($weekStart)->delete();

            foreach ($amStaff as $userId) {
                StaffShiftAssignment::create([
                    'user_id' => $userId,
                    'week_start' => $weekStart,
                    'shift' => 'am',
                    'start_time' => $validated['am_start'],
                    'end_time' => $validated['am_end'],
                ]);
            }

            foreach ($pmStaff as $userId) {
                StaffShiftAssignment::create([
                    'user_id' => $userId,
                    'week_start' => $weekStart,
                    'shift' => 'pm',
                    'start_time' => $validated['pm_start'],
                    'end_time' => $validated['pm_end'],
                ]);
            }
        });

        return back()->with('success', 'Weekly roster saved for the week of '.$weekStart.'.');
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
