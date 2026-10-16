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
        // Dropdown selections persist per session (search `q` never does):
        // a request WITH the param uses it and stores it; without it the
        // stored choice applies, then the default.
        $status = $request->query('status', $request->session()->get('filters.change_requests.status', AppointmentChangeRequest::STATUS_PENDING));
        if ($request->query('status') !== null) {
            $request->session()->put('filters.change_requests.status', $status);
        }
        $type = $request->query('type', $request->session()->get('filters.change_requests.type', 'all'));
        if ($request->query('type') !== null) {
            $request->session()->put('filters.change_requests.type', $type);
        }
        $q = trim((string) $request->query('q', ''));

        return view('admin.appointment-requests.index', [
            'requests' => AppointmentChangeRequest::with([
                'appointment.service:id,name,duration_minutes',
                'appointment.personnel:id,name',
                'appointment.payments',
                'appointment.payment',
                'requester:id,name',
                'reviewer:id,name',
                'requestedPersonnel:id,name',
            ])
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->when($type !== 'all', fn ($query) => $query->where('type', $type))
                ->when($q !== '', fn ($query) => $query->where(function ($query) use ($q) {
                    $query->whereHas('requester', fn ($query) => $query->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('appointment.service', fn ($query) => $query->where('name', 'like', "%{$q}%"));
                }))
                ->orderByDesc('created_at')
                ->paginate(15)
                ->withQueryString(),
            'currentStatus' => $status,
            'currentType' => $type,
            'currentQ' => $q,
            'pendingCount' => AppointmentChangeRequest::where('status', AppointmentChangeRequest::STATUS_PENDING)->count(),
            'totalRequests' => AppointmentChangeRequest::count(),
        ]);
    }

    public function review(Request $request, AppointmentChangeRequest $changeRequest)
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_note' => ['nullable', 'string', 'max:1000'],
            'refund_decision' => ['nullable', 'string'],
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
            // The approve modal for cancellations carries a REQUIRED
            // refund decision instead of a note (the reject modal keeps
            // its optional note).
            $approval = $request->validate([
                'refund_decision' => ['required', Rule::in(['refundable', 'non_refundable'])],
            ]);
            $refundDecision = $approval['refund_decision'];

            $appointment->loadMissing(['service', 'payments', 'payment']);
            $paidTotal = $appointment->verifiedPaid();

            // Zombie requests: the appointment already reached a terminal
            // state through another path (e.g. its pending payment was
            // rejected in Payments). Never leave the request Pending.
            if ($appointment->status->isTerminal()) {
                $label = $appointment->status->label();

                if ($appointment->status === AppointmentStatus::Cancelled) {
                    DB::transaction(function () use ($appointment, $changeRequest, $request, $refundDecision, $paidTotal) {
                        $this->finish(
                            $changeRequest,
                            AppointmentChangeRequest::STATUS_APPROVED,
                            $request->user()->id,
                            null,
                            $refundDecision
                        );

                        $this->applyCancellationRefund($appointment, $refundDecision, $paidTotal);
                    });

                    $this->notifyRequester(
                        $changeRequest,
                        'Appointment Cancelled',
                        $this->cancellationApprovalBody($appointment, $refundDecision, $paidTotal)
                    );

                    return back()->with('success', 'That appointment was already cancelled — the request has been closed as approved.');
                }

                $this->finish(
                    $changeRequest,
                    AppointmentChangeRequest::STATUS_REJECTED,
                    $request->user()->id,
                    'Appointment already '.$label
                );

                $this->notifyRequester(
                    $changeRequest,
                    'Cancellation request declined',
                    'Your cancellation request for '.($appointment->service->name ?? 'your appointment')
                        .' was closed because the appointment is already '.$label.'.'
                );

                return back()->with('success', 'That appointment is already '.$label.' — the request has been closed.');
            }

            DB::transaction(function () use ($appointment, $changeRequest, $request, $refundDecision, $paidTotal) {
                $this->appointmentService->transition(
                    $appointment,
                    AppointmentStatus::Cancelled,
                    $request->user(),
                    [
                        'rejection_reason' => $changeRequest->reason ?: 'Client requested cancellation (approved by management).',
                        // Single notification for the outcome (sent by the
                        // transition itself) with decision-aware wording.
                        'notify_body' => $this->cancellationApprovalBody($appointment, $refundDecision, $paidTotal),
                    ],
                );

                $this->finish(
                    $changeRequest,
                    AppointmentChangeRequest::STATUS_APPROVED,
                    $request->user()->id,
                    null,
                    $refundDecision
                );

                $this->applyCancellationRefund($appointment->fresh(['payments', 'payment', 'service']), $refundDecision, $paidTotal);
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

    private function finish(AppointmentChangeRequest $changeRequest, string $status, int $reviewerId, ?string $note, ?string $refundDecision = null): void
    {
        $changeRequest->update([
            'status' => $status,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_note' => $note,
            'refund_decision' => $refundDecision,
        ]);
    }

    /**
     * Refund marking for an approved cancellation: when the decision is
     * refundable and the appointment held verified money (X > 0), flag the
     * primary payment as refund-requested so the existing Payments UI
     * (refund line + "Mark refund sent" + RefundSentMail flow) picks it up.
     * Non-refundable decisions and nothing-paid bookings mark nothing.
     */
    private function applyCancellationRefund(Appointment $appointment, string $refundDecision, float $paidTotal): void
    {
        if ($refundDecision !== 'refundable' || $paidTotal <= 0) {
            return;
        }

        $primary = $appointment->payment;

        if ($primary === null || $primary->refund_status !== null) {
            return;
        }

        $primary->update([
            'refund_status' => 'requested',
            'refund_requested_at' => now(),
        ]);
    }

    /**
     * Single-notification body for a cancellation approval outcome,
     * reflecting the refund decision (refundable mentions the ₱X refund
     * that will be sent back; non-refundable states the policy).
     */
    private function cancellationApprovalBody(Appointment $appointment, string $refundDecision, float $paidTotal): string
    {
        $service = $appointment->service->name ?? 'your appointment';

        if ($refundDecision === 'refundable' && $paidTotal > 0) {
            $amount = number_format($paidTotal, 2);

            return "Your booking for {$service} has been cancelled. Your ₱{$amount} refund will be sent back to your payment method.";
        }

        if ($refundDecision === 'refundable') {
            return "Your booking for {$service} has been cancelled. No payment was made, so no refund applies.";
        }

        return "Your booking for {$service} has been cancelled. The down payment is non-refundable (per booking policy).";
    }

    private function notifyRequester(AppointmentChangeRequest $changeRequest, string $title, string $body): void
    {
        $client = $changeRequest->requester;

        if ($client !== null) {
            $this->notificationService->notify($client, $title, $body);
        }
    }
}
