<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Notification;
use App\Models\Service;
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
        $appointments = Appointment::with(['user', 'service', 'personnel', 'payment'])
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
            $methodLabel = $payment ? match ($payment->method) {
                'cash' => 'Cash', 'gcash' => 'GCash', 'bank_transfer' => 'Bank transfer', 'card' => 'Card',
                default => ucfirst((string) $payment->method),
            } : null;

            return [
                'id' => $appointment->id,
                'title' => trim(($appointment->clientName() ?? 'Client').' · '.($appointment->service->name ?? '')),
                'start' => $start,
                'end' => Carbon::parse($start)->addMinutes($duration)->toIso8601String(),
                'backgroundColor' => $bg,
                'borderColor' => $border,
                'textColor' => $fg,
                'classNames' => ['nx-evt', 'nx-evt-'.$statusKey],
                'extendedProps' => [
                    'client' => $appointment->clientName() ?? 'Client',
                    'phone' => $appointment->user?->clientProfile?->mobile_number ?? $appointment->walk_in_phone,
                    'returning' => in_array($appointment->user_id, $returningUserIds, true),
                    'service' => $appointment->service->name ?? '—',
                    'durationMins' => $duration,
                    'price' => $appointment->service->price ? (float) $appointment->service->price : null,
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
                    'notes' => $appointment->notes,
                    // Which status actions the drawer should offer (server still
                    // re-validates each via AppointmentService::transition).
                    // "Start service" is only available when a staff member is assigned.
                    'actions' => match ($appointment->status) {
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
