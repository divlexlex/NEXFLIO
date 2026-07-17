<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Notification;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

    /** FullCalendar event feed. */
    public function feed(Request $request)
    {
        $appointments = Appointment::with(['service:id,name,duration_minutes', 'personnel:id,name'])
            ->whereNotIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::NoShow])
            ->when($request->query('start'), fn ($query, $start) => $query->where('appointment_date', '>=', $start))
            ->when($request->query('end'), fn ($query, $end) => $query->where('appointment_date', '<=', $end))
            ->get();

        return response()->json($appointments->map(function (Appointment $appointment) {
            $date = $appointment->appointment_date->toDateString();
            $start = "{$date}T{$appointment->start_time}";
            $duration = $appointment->service->duration_minutes ?? 60;

            return [
                'id' => $appointment->id,
                'title' => trim(($appointment->clientName() ?? 'Client') . ' · ' . ($appointment->service->name ?? '')),
                'start' => $start,
                'end' => \Carbon\Carbon::parse($start)->addMinutes($duration)->toIso8601String(),
                'color' => match ($appointment->status) {
                    AppointmentStatus::Unverified => '#fd7e14',
                    AppointmentStatus::Booked => '#198754',
                    AppointmentStatus::InService => '#0d6efd',
                    default => '#9C7A54',
                },
                'extendedProps' => [
                    'status' => $appointment->status->value,
                    'personnel' => $appointment->personnel->name ?? null,
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
                    . "{$appointment->appointment_date->toDateString()} at {$appointment->start_time}.",
            ]);
        }

        return back()->with('success', 'Appointment updated.');
    }
}
