<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlockedSlot;
use App\Models\BusinessHours;
use App\Models\Position;
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
        // any date inside it) prefills the roster rows from that week while
        // Save still writes to ?week's Sunday. Rows are individual
        // per-person shifts (user + their own start/end); the legacy shift
        // column is ignored.
        $weekStart = $this->normalizeWeekParam($request->query('week'))
            ?? StaffShiftAssignment::weekStartOf(now()->toDateString());

        $copyFrom = $this->normalizeWeekParam($request->query('copy_from'));
        $sourceWeek = $copyFrom ?? $weekStart;

        $rosterWeekStart = $weekStart;
        $rosterWeekEnd = \Carbon\Carbon::parse($weekStart)->addDays(6)->toDateString();
        $rosterPrevWeek = \Carbon\Carbon::parse($weekStart)->subDays(7)->toDateString();
        $rosterNextWeek = \Carbon\Carbon::parse($weekStart)->addDays(7)->toDateString();
        $rosterWeekLabel = 'Week of '.\Carbon\Carbon::parse($weekStart)->format('M j')
            .' – '.\Carbon\Carbon::parse($rosterWeekEnd)->format('j, Y');

        $byUser = $staff->keyBy('id');
        $rowShape = function ($userId, $start, $end) use ($byUser) {
            $member = $byUser->get((int) $userId);
            if (! $member) {
                return null;
            }

            return [
                'user_id' => (int) $userId,
                'name' => $member->fullName(),
                'position' => $member->staffProfile->position ?? 'Staff',
                'start' => substr((string) $start, 0, 5) ?: '10:00',
                'end' => substr((string) $end, 0, 5) ?: '21:00',
            ];
        };

        // After a failed save, redisplay the submitted rows instead of the
        // stored ones so nothing the manager typed is lost.
        $old = $request->old('assignments');
        if (is_array($old) && $old !== []) {
            $rosterAssignments = collect($old)
                ->map(fn ($row) => $rowShape($row['user_id'] ?? 0, $row['start_time'] ?? null, $row['end_time'] ?? null))
                ->filter()
                ->values()
                ->all();
        } else {
            $rosterAssignments = StaffShiftAssignment::forWeek($sourceWeek)
                ->orderBy('user_id')
                ->get()
                ->map(fn ($assignment) => $rowShape($assignment->user_id, $assignment->start_time, $assignment->end_time))
                ->filter()
                ->values()
                ->all();
        }

        // Locked vs edit mode: a week that already has saved assignments
        // opens read-only unless the manager asks to edit (?edit=1). A week
        // with no roster yet, a copy-from prefill, or a failed save (old
        // input present) opens directly in edit mode.
        $rosterHasAssignments = StaffShiftAssignment::weekHasRoster($weekStart);
        $rosterEditable = $request->boolean('edit')
            || $copyFrom !== null
            || ! $rosterHasAssignments
            || (is_array($old) && $old !== []);

        $rosterStaff = $staff
            ->filter(fn ($member) => ($member->staffProfile?->employment_status ?? null) === 'active')
            ->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->fullName(),
                'position' => $member->staffProfile->position ?? 'Staff',
            ])
            ->values()
            ->all();

        // Position names for the roster picker pills + grouping order —
        // every position in the table, so newly added ones appear with no
        // code changes (see App\Models\Position).
        $rosterPositions = Position::orderBy('id')->pluck('name')->all();

        // Business Hours lock: saved hours open read-only unless the manager
        // asks to edit (?edit=1) — the same flag the Weekly Roster lock uses.
        $businessHoursEditable = $request->boolean('edit');

        return view('admin.availability.index', compact(
            'businessHours', 'staff', 'schedules', 'services', 'blockedSlots',
            'rosterWeekStart', 'rosterWeekEnd', 'rosterPrevWeek', 'rosterNextWeek',
            'rosterWeekLabel', 'rosterAssignments', 'rosterStaff', 'rosterPositions',
            'rosterHasAssignments', 'rosterEditable', 'businessHoursEditable'
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
        // Any time is allowed; a close EARLIER than open is valid and means
        // the business closes after midnight. The only invalid pair is a
        // zero-length day (close == open) — see the check below.
        $validated = $request->validate([
            'hours'   => 'required|array|size:7',
            'hours.*.is_open'   => 'required|boolean',
            'hours.*.open_at'   => 'required_if:hours.*.is_open,1|nullable|date_format:H:i',
            'hours.*.close_at'  => 'required_if:hours.*.is_open,1|nullable|date_format:H:i',
        ]);

        foreach ($validated['hours'] as $day => $data) {
            if (($data['is_open'] ?? false) && ($data['open_at'] ?? null) === ($data['close_at'] ?? null)) {
                throw ValidationException::withMessages([
                    "hours.{$day}.close_at" => 'Closing time cannot be the same as opening time.',
                ]);
            }
        }

        DB::transaction(function () use ($validated) {
            foreach ($validated['hours'] as $day => $data) {
                $defaults = BusinessHours::defaultsForDay((int) $day);

                BusinessHours::updateOrCreate(
                    ['day_of_week' => $day],
                    [
                        'is_open'  => $data['is_open'] ?? false,
                        'open_at'  => $data['open_at'] ?? $defaults['open_at'],
                        'close_at' => $data['close_at'] ?? $defaults['close_at'],
                    ]
                );
            }
        });

        // Land on the locked (read-only) view with the success flash.
        return redirect()
            ->route('admin.availability')
            ->withFragment('tab-hours')
            ->with('success', 'Business hours updated.');
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

    // ─── Weekly Roster (individual per-person shifts) ────────────────

    /**
     * Save one week's roster as individual rows: each entry is one staff
     * member with their own start/end times for the whole week. Existing
     * assignments for that week are replaced wholesale in a single
     * transaction. week_start accepts any date and is normalized to its
     * Sunday. An empty (or missing) assignments list clears the week, which
     * falls back to the legacy weekday schedules exactly as before.
     */
    public function updateWeeklyRoster(Request $request)
    {
        $validated = $request->validate([
            'week_start' => 'required|date',
            'assignments' => 'nullable|array',
            'assignments.*.user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role_id', User::ROLE_STAFF)->whereNull('deleted_at'),
            ],
            'assignments.*.start_time' => 'required|date_format:H:i',
            'assignments.*.end_time' => 'required|date_format:H:i',
        ]);

        $rows = collect($validated['assignments'] ?? [])
            ->map(fn ($row) => [
                'user_id' => (int) $row['user_id'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
            ])
            ->values();

        $duplicates = $rows->pluck('user_id')
            ->countBy()
            ->filter(fn ($count) => $count > 1)
            ->keys();
        if ($duplicates->isNotEmpty()) {
            $names = User::whereIn('id', $duplicates->all())->orderBy('name')->pluck('name')->all();

            throw ValidationException::withMessages([
                'assignments' => 'Each person can appear only once per week: '.implode(', ', $names).'.',
            ]);
        }

        foreach ($rows as $row) {
            if ($row['end_time'] <= $row['start_time']) {
                $name = User::where('id', $row['user_id'])->value('name') ?? "Staff #{$row['user_id']}";

                throw ValidationException::withMessages([
                    'assignments' => "End time must be after start time for {$name} (no overnight shifts).",
                ]);
            }
        }

        $inactive = User::whereIn('id', $rows->pluck('user_id')->all())
            ->whereDoesntHave('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
            ->orderBy('name')
            ->pluck('name')
            ->all();
        if ($inactive !== []) {
            throw ValidationException::withMessages([
                'assignments' => 'Only active staff can be rostered: '.implode(', ', $inactive).'.',
            ]);
        }

        $weekStart = StaffShiftAssignment::weekStartOf($validated['week_start']);

        DB::transaction(function () use ($weekStart, $rows) {
            StaffShiftAssignment::forWeek($weekStart)->delete();

            foreach ($rows as $row) {
                StaffShiftAssignment::create([
                    'user_id' => $row['user_id'],
                    'week_start' => $weekStart,
                    'shift' => null,
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                ]);
            }
        });

        return redirect()
            ->route('admin.availability', ['week' => $weekStart])
            ->withFragment('tab-staff')
            ->with('success', 'Weekly roster saved for the week of '.$weekStart.'.');
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
