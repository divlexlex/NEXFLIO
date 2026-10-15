<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\Service;
use App\Models\StaffShiftAssignment;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $appointments = Appointment::with(['user', 'service', 'personnel', 'payment', 'payments'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('appointment_date')
            ->paginate(15)
            ->withQueryString();

        return view('admin.appointments.index', [
            'appointments' => $appointments,
            'services' => Service::where('status', 'active')->orderBy('name')->get(),
            'personnel' => User::where('role_id', User::ROLE_STAFF)
                ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
                ->orderBy('name')
                ->get(),
            'statuses' => AppointmentStatus::cases(),
        ]);
    }

    /**
     * Master Schedule — weekly monitoring grid for Owner/Manager: one row
     * per active staff member with their Weekly Roster shift and per-day
     * appointments. Read-only; weeks always run Sunday–Saturday.
     */
    public function masterSchedule(Request $request)
    {
        try {
            $weekStart = StaffShiftAssignment::weekStartOf($request->query('week', now()->toDateString()));
        } catch (\Throwable) {
            $weekStart = StaffShiftAssignment::weekStartOf(now()->toDateString());
        }

        $sunday = Carbon::parse($weekStart);
        $weekEnd = $sunday->copy()->addDays(6)->toDateString();

        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $weekDays[] = $sunday->copy()->addDays($i)->toDateString();
        }

        $staff = User::where('role_id', User::ROLE_STAFF)
            ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
            ->with('staffProfile')
            ->orderBy('name')
            ->get();

        $assignments = StaffShiftAssignment::whereIn('user_id', $staff->pluck('id'))
            ->where('week_start', $weekStart)
            ->get()
            ->keyBy('user_id');

        $appointments = Appointment::with(['service:id,name,duration_minutes', 'user:id,name'])
            ->whereIn('personnel_id', $staff->pluck('id'))
            ->whereDate('appointment_date', '>=', $weekStart)
            ->whereDate('appointment_date', '<=', $weekEnd)
            ->whereNotIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::NoShow])
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn ($appointment) => $appointment->personnel_id.'|'.$appointment->appointment_date->toDateString());

        $leaveDays = [];
        LeaveRequest::whereIn('user_id', $staff->pluck('id'))
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('end_date', '>=', $weekStart)
            ->whereDate('start_date', '<=', $weekEnd)
            ->get()
            ->each(function ($leave) use (&$leaveDays, $weekStart, $weekEnd) {
                $day = max($leave->start_date->toDateString(), $weekStart);
                $last = min($leave->end_date->toDateString(), $weekEnd);
                while ($day <= $last) {
                    $leaveDays[$leave->user_id][] = $day;
                    $day = Carbon::parse($day)->addDay()->toDateString();
                }
            });

        $rows = $staff->map(function ($member) use ($assignments, $appointments, $leaveDays, $weekDays) {
            $days = [];
            foreach ($weekDays as $day) {
                $days[$day] = [
                    'appointments' => $appointments->get($member->id.'|'.$day, collect())->values(),
                    'on_leave' => in_array($day, $leaveDays[$member->id] ?? [], true),
                ];
            }

            return ['staff' => $member, 'shift' => $assignments->get($member->id), 'days' => $days];
        });

        $selected = null;
        if ($request->query('staff') && $request->query('date')) {
            $member = $staff->firstWhere('id', (int) $request->query('staff'));
            $date = $request->query('date');
            if ($member && in_array($date, $weekDays, true)) {
                $selected = [
                    'staff' => $member,
                    'date' => $date,
                    'shift' => $assignments->get($member->id),
                    'appointments' => $appointments->get($member->id.'|'.$date, collect())->values(),
                    'on_leave' => in_array($date, $leaveDays[$member->id] ?? [], true),
                ];
            }
        }

        return view('admin.master-schedule', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'prevWeek' => $sunday->copy()->subWeek()->toDateString(),
            'nextWeek' => $sunday->copy()->addWeek()->toDateString(),
            'weekDays' => $weekDays,
            'weekLabel' => 'Week of '.$sunday->format('M j').' – '.$sunday->copy()->addDays(6)->format('M j, Y'),
            'rows' => $rows,
            'selected' => $selected,
        ]);
    }

    /**
     * FullCalendar event feed — carries everything the details drawer on the
     * Appointments page needs in extendedProps, so clicking an event never
     * needs a second request. Cancelled / no-show are included (greyed) so
     * the calendar legend is meaningful and past cancellations stay visible.
     */
    public function feed(Request $request)
    {
        $appointments = Appointment::with([
            'user:id,name',
            'user.clientProfile:id,user_id,mobile_number',
            'service:id,name,duration_minutes,price',
            'personnel:id,name',
            'payment',
            'payments',
            'address',
        ])
            ->when($request->query('start'), fn ($query, $start) => $query->where('appointment_date', '>=', $start))
            ->when($request->query('end'), fn ($query, $end) => $query->where('appointment_date', '<=', $end))
            ->get();

        // One grouped query for the "Returning customer" flag instead of N.
        $returningUserIds = Appointment::query()
            ->whereIn('user_id', $appointments->pluck('user_id')->filter()->unique())
            ->selectRaw('user_id, COUNT(*) as c')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id')
            ->all();

        return response()->json($appointments->map(function (Appointment $appointment) use ($returningUserIds) {
            $date = $appointment->appointment_date->toDateString();
            $start = "{$date}T{$appointment->start_time}";
            $duration = $appointment->service->duration_minutes ?? 60;

            $isWalkIn = $appointment->user_id === null;
            $bookingType = $isWalkIn ? 'Walk-in' : ($appointment->address ? 'Home Service' : 'Online Booking');

            // Light-mode chip colours (FullCalendar renders these inline); the
            // dark-mode equivalents are CSS overrides keyed off the classNames
            // below — see admin/appointments/index.blade.php.
            $statusKey = $isWalkIn && ! $appointment->status->isTerminal() ? 'walkin' : $appointment->status->value;
            [$bg, $border, $fg] = match ($statusKey) {
                'walkin' => ['#deeafe', '#aec8f7', '#0d5bd6'],
                'unverified' => ['#fcebd2', '#ecc78f', '#8a5a16'],
                'booked' => ['#dcf0e2', '#a9d9bc', '#1f6b41'],
                'in-service' => ['#deeafe', '#aec8f7', '#1b4fc0'],
                'completed' => ['#eae1f6', '#cdb8ec', '#5e3a97'],
                'cancelled' => ['#ececec', '#d9d9d9', '#7a7a7a'],
                'no-show' => ['#f6e5e3', '#e4c0ba', '#93534a'],
                default => ['#e9e2d8', '#d8ccbc', '#5b5148'],
            };

            $payment = $appointment->payment;
            $methodLabel = $payment
                ? config('payment.methods.'.$payment->method, ucfirst((string) $payment->method))
                : null;

            // Money decisions happen in Payments: an Unverified booking
            // whose primary payment is still pending is locked here — no
            // Confirm / Reject actions, just a hint to use Payments. Cash
            // bookings carry no online payment to verify, so they stay
            // actionable here (confirm/cancel from the drawer).
            $verificationLocked = $appointment->status === AppointmentStatus::Unverified
                && $payment?->status === PaymentStatus::Pending
                && $payment?->method !== 'cash';

            return [
                'id' => $appointment->id,
                'title' => $appointment->referenceCode().' · '.($appointment->clientName() ?? 'Client').' · '.($appointment->displayServiceName()),
                'start' => $start,
                'end' => Carbon::parse($start)->addMinutes($duration)->toIso8601String(),
                'backgroundColor' => $bg,
                'borderColor' => $border,
                'textColor' => $fg,
                'classNames' => ['nx-evt', 'nx-evt-'.$statusKey],
                'extendedProps' => [
                    'client' => $appointment->clientName() ?? 'Client',
                    'reference_code' => $appointment->referenceCode(),
                    'phone' => $appointment->user?->clientProfile?->mobile_number ?? $appointment->walk_in_phone,
                    'returning' => in_array($appointment->user_id, $returningUserIds, true),
                    'service' => $appointment->displayServiceName(),
                    'durationMins' => $duration,
                    'price' => $appointment->price !== null ? (float) $appointment->price : null,
                    'bookingType' => $bookingType,
                    'dateLabel' => $appointment->appointment_date->format('M j, Y (D)'),
                    'timeLabel' => Carbon::parse($start)->format('g:i A')
                        .' – '.Carbon::parse($start)->addMinutes($duration)->format('g:i A'),
                    'personnel' => $appointment->personnel->name ?? null,
                    'personnelId' => $appointment->personnel_id,
                    'status' => $appointment->status->value,
                    'statusLabel' => $appointment->status->label(),
                    'isTerminal' => $appointment->status->isTerminal(),
                    'payment' => $payment ? [
                        'label' => $methodLabel.' · '.ucfirst($payment->status->value),
                        'amount' => (float) $payment->amount,
                        'proofUrl' => $payment->proof_path ? Storage::url($payment->proof_path) : null,
                    ] : null,
                    // Payment state for the details drawer: what the client has
                    // paid / still owes. Settlement itself now lives on the
                    // Payments page (PaymentController@index), not here.
                    'paymentState' => $appointment->paymentState(),
                    'balanceDue' => $appointment->balanceDue(),
                    'notes' => $appointment->notes,
                    // Money decisions happen in Payments, not here: an
                    // Unverified booking with a still-pending payment offers
                    // NO status actions in the drawer (see verificationLocked
                    // below) — the updateStatus endpoint refuses them too.
                    // Which status actions the drawer should offer (server
                    // still re-validates each via AppointmentService::transition).
                    // "Start service" is only available when a staff member is assigned.
                    'verificationLocked' => $verificationLocked,
                    'actions' => $verificationLocked ? [] : match ($appointment->status) {
                        AppointmentStatus::Unverified => [
                            ['to' => 'booked', 'label' => 'Confirm booking', 'style' => 'success'],
                            ['to' => 'cancelled', 'label' => 'Reject / Cancel', 'style' => 'outline-danger', 'reason' => true],
                        ],
                        AppointmentStatus::Booked => array_merge(
                            $appointment->personnel_id ? [
                                ['to' => 'in-service', 'label' => 'Start service', 'style' => 'primary'],
                            ] : [],
                            [
                                ['to' => 'no-show', 'label' => 'Mark no-show', 'style' => 'outline-secondary'],
                                ['to' => 'cancelled', 'label' => 'Cancel', 'style' => 'outline-danger'],
                            ]
                        ),
                        AppointmentStatus::InService => [
                            ['to' => 'completed', 'label' => 'Mark completed', 'style' => 'success'],
                            ['to' => 'cancelled', 'label' => 'Cancel', 'style' => 'outline-danger'],
                        ],
                        default => [],
                    },
                ],
            ];
        }));
    }

    /**
     * Walk-in entry: the manager books a client who is standing at the
     * counter — no account, cash paid on the spot, so the appointment starts
     * verified/booked.
     */
    public function storeWalkIn(Request $request)
    {
        $validated = $request->validate([
            'walk_in_name' => 'required|string|max:255',
            'walk_in_phone' => 'nullable|string|max:30',
            'service_id' => [
                'required',
                Rule::exists('services', 'id')->where('status', 'active')->whereNull('deleted_at'),
            ],
            'personnel_id' => [
                'required',
                Rule::exists('users', 'id')->where('role_id', User::ROLE_STAFF)->whereNull('deleted_at'),
            ],
            'appointment_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($request, $validated) {
            $appointment = Appointment::create([
                'user_id' => null,
                'walk_in_name' => $validated['walk_in_name'],
                'walk_in_phone' => $validated['walk_in_phone'] ?? null,
                'service_id' => $validated['service_id'],
                'personnel_id' => $validated['personnel_id'],
                'appointment_date' => $validated['appointment_date'],
                'start_time' => $validated['start_time'],
                'notes' => $validated['notes'] ?? null,
                'status' => AppointmentStatus::Booked,
            ]);

            // Client-facing Appointment ID, assigned here (the id only
            // exists post-insert) and saved in the same transaction.
            $appointment->update(['reference_code' => $appointment->referenceCode()]);

            $appointment->payment()->create([
                'amount' => $appointment->service->price,
                'method' => 'cash',
                'status' => PaymentStatus::Verified,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
            ]);
        });

        return back()->with('success', 'Walk-in appointment created.');
    }

    /**
     * Status action from the Appointments calendar details drawer — Confirm
     * (verify payment → Booked), Mark Completed, or Cancel. Everything runs
     * through AppointmentService::transition so payment verification,
     * commission accrual, and client notifications stay consistent with the
     * mobile app and the Payments screen.
     */
    public function updateStatus(Request $request, AppointmentService $appointmentService, $id)
    {
        $appointment = Appointment::with(['service', 'personnel', 'payment', 'user'])->findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                AppointmentStatus::Booked->value,
                AppointmentStatus::InService->value,
                AppointmentStatus::Completed->value,
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
            ])],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        // Money decisions happen in Payments, not here: an Unverified
        // booking with a still-pending ONLINE payment can only leave
        // verification through the Payments verify/reject routes (the API
        // keeps its own path — this guard is web-only by scope). A Cash
        // booking has no online payment to verify, so it stays confirmable
        // and cancellable here.
        if ($appointment->status === AppointmentStatus::Unverified
            && $appointment->payment?->status === PaymentStatus::Pending
            && $appointment->payment?->method !== 'cash'
            && in_array($validated['status'], [
                AppointmentStatus::Booked->value,
                AppointmentStatus::Cancelled->value,
            ], true)) {
            return back()->withErrors([
                'status' => 'This booking still has a pending payment — verify or reject it in Payments first.',
            ]);
        }

        try {
            $appointmentService->transition(
                $appointment,
                AppointmentStatus::from($validated['status']),
                $request->user(),
                ['rejection_reason' => $validated['rejection_reason'] ?? null],
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (AuthorizationException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Appointment updated to "'.AppointmentStatus::from($validated['status'])->label().'".');
    }

    /**
     * Record a branch settlement: money collected at the spa against the
     * remaining balance (cash down-payment bookings, reservation-fee
     * balances). Creates an immediately-Verified payment row attributed to
     * the current manager — the appointment status itself never moves here.
     * Refused when nothing is owed or the booking is Cancelled / No-Show.
     */
    public function recordBranchPayment(Request $request, $id)
    {
        $appointment = Appointment::with(['service', 'payments'])->findOrFail($id);

        if (in_array($appointment->status, [AppointmentStatus::Cancelled, AppointmentStatus::NoShow], true)) {
            return back()->withErrors(['branch_payment' => 'Cannot record a payment on a cancelled or no-show appointment.']);
        }

        $balance = $appointment->balanceDue();

        if ($balance <= 0) {
            return back()->withErrors(['branch_payment' => 'This booking has no remaining balance.']);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', "max:{$balance}"],
            'method' => ['sometimes', Rule::in(array_keys(config('payment.methods', [])))],
        ]);

        $appointment->payments()->create([
            'kind' => 'branch',
            'amount' => $validated['amount'],
            'method' => $validated['method'] ?? 'cash',
            'status' => PaymentStatus::Verified,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        return back()->with('success', 'Branch payment of ₱'.number_format((float) $validated['amount'], 2).' recorded.');
    }

    /**
     * Check-out: settle the appointment's balance and complete the visit in
     * one step. The money half is identical to recordBranchPayment (an
     * immediately-Verified kind='branch' row); the status half reuses
     * AppointmentService::transition — the same path as a normal completion,
     * so commission accrual and notifications are indistinguishable
     * (Booked steps through In-Service first, since Booked cannot move
     * directly to Completed). Partial amounts are allowed: the visit still
     * completes and the remainder stays collectible.
     *
     * Refused for Cancelled / No-Show (like settlement) and for Unverified
     * (a pending online payment must go through the Payments queue first —
     * check-out never bypasses verification).
     */
    public function checkout(Request $request, AppointmentService $appointmentService, $id)
    {
        $appointment = Appointment::with(['service', 'personnel', 'payments', 'user'])->findOrFail($id);

        if (in_array($appointment->status, [AppointmentStatus::Cancelled, AppointmentStatus::NoShow, AppointmentStatus::Unverified], true)) {
            return back()->withErrors(['checkout' => 'Only Booked, In-Service, or Completed appointments can be checked out.']);
        }

        $balance = $appointment->balanceDue();

        $validated = $request->validate([
            'amount' => $balance > 0
                ? ['required', 'numeric', 'min:0.01', "max:{$balance}"]
                : ['nullable', 'numeric', 'min:0.01', "max:{$balance}"],
            'method' => ['sometimes', Rule::in(array_keys(config('payment.methods', [])))],
        ]);

        try {
            DB::transaction(function () use ($request, $appointmentService, $appointment, $balance, $validated) {
                if ($balance > 0) {
                    $appointment->payments()->create([
                        'kind' => 'branch',
                        'amount' => $validated['amount'],
                        'method' => $validated['method'] ?? 'cash',
                        'status' => PaymentStatus::Verified,
                        'verified_by' => $request->user()->id,
                        'verified_at' => now(),
                    ]);
                }

                if ($appointment->status === AppointmentStatus::Booked) {
                    $appointmentService->transition($appointment, AppointmentStatus::InService, $request->user());
                }

                if ($appointment->status === AppointmentStatus::InService) {
                    $appointmentService->transition($appointment, AppointmentStatus::Completed, $request->user());
                }
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (AuthorizationException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        }

        return back()->with('success', $balance > 0
            ? 'Checked out — ₱'.number_format((float) $validated['amount'], 2).' collected.'
            : 'Checked out — appointment completed.');
    }

    /**
     * Manager override: reschedule and/or reassign an active appointment.
     * The Auditable trait records the before/after values automatically.
     */
    public function override(Request $request, $id)
    {
        $appointment = Appointment::with('service')->findOrFail($id);

        if ($appointment->status->isTerminal()) {
            return back()->withErrors(['override' => 'Finished appointments cannot be modified.']);
        }

        $validated = $request->validate([
            'appointment_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'personnel_id' => [
                'required',
                Rule::exists('users', 'id')->where('role_id', User::ROLE_STAFF)->whereNull('deleted_at'),
            ],
        ]);

        $appointment->update($validated);

        if ($appointment->user_id !== null) {
            Notification::create([
                'user_id' => $appointment->user_id,
                'title' => 'Appointment updated',
                'body' => "Your booking for {$appointment->service->name} was moved to "
                    ."{$appointment->appointment_date->toDateString()} at {$appointment->start_time}.",
            ]);
        }

        return back()->with('success', 'Appointment updated.');
    }

    /**
     * Assign or reassign a staff member to an appointment — can be used even
     * on already-approved (Booked) or in-service appointments.
     */
    public function assignPersonnel(Request $request, $id)
    {
        $appointment = Appointment::with('service')->findOrFail($id);

        if ($appointment->status->isTerminal()) {
            return back()->withErrors(['assign' => 'Cannot assign staff to a finished appointment.']);
        }

        $validated = $request->validate([
            'personnel_id' => [
                'required',
                Rule::exists('users', 'id')->where('role_id', User::ROLE_STAFF)->whereNull('deleted_at'),
            ],
        ]);

        $appointment->update(['personnel_id' => $validated['personnel_id']]);

        if ($appointment->user_id !== null) {
            $staffName = User::find($validated['personnel_id'])?->name ?? 'a staff member';
            Notification::create([
                'user_id' => $appointment->user_id,
                'title' => 'Staff assigned',
                'body' => "{$staffName} has been assigned to your {$appointment->service->name} booking.",
            ]);
        }

        return back()->with('success', 'Staff assigned to appointment.');
    }
}
