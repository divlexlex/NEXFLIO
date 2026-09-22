<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Services\AppointmentService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Manager review queue for Client-raised appointment change requests
 * (cancellation / reschedule). Owner + Manager (role 1, 2). Approving a
 * cancellation routes through AppointmentService::transition so payment and
 * notification side effects match every other cancel path; approving a
 * reschedule applies the new date/time/staff directly (the Auditable trait
 * records the before/after) after re-checking the exact-slot collision rule.
 */
class AppointmentRequestController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request)
    {
        $status = $request->query('status', AppointmentChangeRequest::STATUS_PENDING);

        return view('admin.appointment-requests.index', [
            'requests' => AppointmentChangeRequest::with([
                'appointment.service:id,name,duration_minutes',
                'appointment.personnel:id,name',
                'requester:id,name',
                'reviewer:id,name',
                'requestedPersonnel:id,name',
            ])
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->orderByDesc('created_at')
                ->paginate(15)
                ->withQueryString(),
            'currentStatus' => $status,
            'pendingCount' => AppointmentChangeRequest::where('status', AppointmentChangeRequest::STATUS_PENDING)->count(),
        ]);
    }

    public function review(Request $request, AppointmentChangeRequest $changeRequest)
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $changeRequest->isPending()) {
            return back()->withErrors(['decision' => 'This request has already been reviewed.']);
        }

        $appointment = $changeRequest->appointment;

        if ($appointment === null) {
            return back()->withErrors(['decision' => 'The appointment for this request no longer exists.']);
        }

        if ($validated['decision'] === 'reject') {
            $this->finish($changeRequest, AppointmentChangeRequest::STATUS_REJECTED, $request->user()->id, $validated['review_note'] ?? null);

            $this->notifyRequester(
                $changeRequest,
                $changeRequest->isReschedule() ? 'Reschedule request declined' : 'Cancellation request declined',
                'Your request for '.($appointment->service->name ?? 'your appointment').' was not approved.'
                    .($validated['review_note'] ? " Note: {$validated['review_note']}" : '')
            );

            return back()->with('success', 'Request rejected.');
        }

        // ----- approve -----
        if ($changeRequest->type === AppointmentChangeRequest::TYPE_CANCELLATION) {
            if ($appointment->status->isTerminal()) {
                return back()->withErrors(['decision' => 'That appointment is already '.$appointment->status->label().'.']);
            }

            DB::transaction(function () use ($appointment, $changeRequest, $request) {
                $this->appointmentService->transition(
                    $appointment,
                    AppointmentStatus::Cancelled,
                    $request->user(),
                    ['rejection_reason' => $changeRequest->reason ?: 'Client requested cancellation (approved by management).'],
                );

                $this->finish($changeRequest, AppointmentChangeRequest::STATUS_APPROVED, $request->user()->id, $request->input('review_note'));
            });

            return back()->with('success', 'Cancellation approved — the appointment is now cancelled.');
        }

        // reschedule
        $targetDate = $changeRequest->requested_date?->toDateString();
        $targetTime = $changeRequest->requested_start_time;
        $targetPersonnel = $changeRequest->requested_personnel_id ?? $appointment->personnel_id;

        if (! $targetDate || ! $targetTime) {
            return back()->withErrors(['decision' => 'This reschedule request is missing a date or time.']);
        }

        if ($appointment->status->isTerminal()) {
            return back()->withErrors(['decision' => 'That appointment is already '.$appointment->status->label().'.']);
        }

        $clash = Appointment::query()
            ->where('personnel_id', $targetPersonnel)
            ->whereDate('appointment_date', $targetDate)
            ->where('start_time', $targetTime)
            ->where('id', '!=', $appointment->id)
            ->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
            ->exists();

        if ($clash) {
            return back()->withErrors([
                'decision' => 'That staff member already has an appointment at the requested date and time. '
                    .'Use "Override" on the Appointments page to place it on a different slot instead.',
            ]);
        }

        DB::transaction(function () use ($appointment, $changeRequest, $request, $targetDate, $targetTime, $targetPersonnel) {
            $appointment->update([
                'appointment_date' => $targetDate,
                'start_time' => $targetTime,
                'personnel_id' => $targetPersonnel,
            ]);

            $this->finish($changeRequest, AppointmentChangeRequest::STATUS_APPROVED, $request->user()->id, $request->input('review_note'));
        });

        $this->notifyRequester(
            $changeRequest,
            'Reschedule approved',
            'Your appointment for '.($appointment->service->name ?? 'your service').' is now on '
                .Carbon::parse($targetDate)->format('F j, Y').' at '
                .Carbon::parse($targetTime)->format('g:i A').'.'
        );

        return back()->with('success', 'Reschedule approved — the appointment has been moved.');
    }

    private function finish(AppointmentChangeRequest $changeRequest, string $status, int $reviewerId, ?string $note): void
    {
        $changeRequest->update([
            'status' => $status,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }

    private function notifyRequester(AppointmentChangeRequest $changeRequest, string $title, string $body): void
    {
        $client = $changeRequest->requester;

        if ($client !== null) {
            $this->notificationService->notify($client, $title, $body);
        }
    }
}
