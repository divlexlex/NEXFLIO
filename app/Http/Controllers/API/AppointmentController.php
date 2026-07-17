<?php

namespace App\Http\Controllers\API;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteAppointmentRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentStatusRequest;
use App\Mail\BookingReceivedMail;
use App\Models\Appointment;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function store(StoreAppointmentRequest $request)
    {
        $validated = $request->validated();

        $appointment = DB::transaction(function () use ($request, $validated) {
            $appointment = Appointment::create([
                'user_id' => $request->user()->id,
                'service_id' => $validated['service_id'],
                'personnel_id' => $validated['personnel_id'],
                'appointment_date' => $validated['appointment_date'],
                'start_time' => $validated['start_time'],
                'notes' => $validated['notes'] ?? null,
                'status' => AppointmentStatus::Unverified,
            ]);

            // Amount is always the service's listed price, taken server-side —
            // never from the request.
            $appointment->payment()->create([
                'amount' => $appointment->service->price,
                'method' => $validated['method'] ?? 'gcash',
                'proof_path' => $request->file('payment_proof')->store('payment_proofs', 'public'),
                'status' => PaymentStatus::Pending,
            ]);

            return $appointment;
        });

        $appointment->load(['user', 'service', 'personnel', 'payment']);

        $this->notificationService->notify(
            $request->user(),
            'Booking received',
            "We received your booking for {$appointment->service->name}. "
                . 'You will be notified once your payment is verified.',
            new BookingReceivedMail($appointment),
            ['appointment_id' => $appointment->id]
        );

        return $appointment;
    }

    /**
     * The bookable staff pool: active employees who are not on break and,
     * when a date is given, have no approved leave covering it.
     */
    public function personnel(Request $request)
    {
        $date = $request->query('date');

        return User::where('role_id', User::ROLE_STAFF)
            ->whereHas('staffProfile', function ($query) {
                $query->where('employment_status', 'active')
                    ->where('is_on_break', false);
            })
            ->when($date, function ($query) use ($date) {
                $query->whereDoesntHave('leaveRequests', function ($leaves) use ($date) {
                    $leaves->where('status', LeaveRequest::STATUS_APPROVED)
                        ->whereDate('start_date', '<=', $date)
                        ->whereDate('end_date', '>=', $date);
                });
            })
            ->select('id', 'name', 'email', 'role_id')
            ->get();
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Appointment::with(['user', 'service', 'personnel', 'payment']);

        if ($user->isClient()) {
            // Clients see their own bookings.
            $query->where('user_id', $user->id);
        } elseif ($user->isStaff()) {
            // Staff see their own schedule, not the whole book.
            $query->where('personnel_id', $user->id);
        }

        return $query->get();
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, $id)
    {
        $appointment = Appointment::with(['service', 'payment'])->findOrFail($id);

        $updated = $this->appointmentService->transition(
            $appointment,
            $request->targetStatus(),
            $request->user(),
            ['rejection_reason' => $request->input('rejection_reason')]
        );

        return response()->json([
            'message' => 'Appointment status updated to ' . $updated->status->value,
            'appointment' => $updated,
        ]);
    }

    public function start(Request $request, $id)
    {
        $appointment = Appointment::with(['service', 'payment'])->findOrFail($id);

        $updated = $this->appointmentService->transition(
            $appointment,
            AppointmentStatus::InService,
            $request->user()
        );

        return response()->json([
            'message' => 'Service started.',
            'appointment' => $updated,
        ]);
    }

    public function complete(CompleteAppointmentRequest $request, $id)
    {
        $appointment = Appointment::with(['service', 'payment', 'personnel.staffProfile'])->findOrFail($id);

        $updated = $this->appointmentService->transition(
            $appointment,
            AppointmentStatus::Completed,
            $request->user(),
            ['items' => $request->validated('items') ?? []]
        );

        return response()->json([
            'message' => 'Service completed.',
            'appointment' => $updated,
        ]);
    }
}
