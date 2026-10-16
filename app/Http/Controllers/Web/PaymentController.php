<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Mail\RefundSentMail;
use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\Payment;
use App\Services\AppointmentService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

/**
 * Manual payment verification queue. Verify/reject go through the same
 * AppointmentService::transition path the mobile API uses.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointmentService)
    {
    }

    /**
     * Payments page, grouped as ONE card per appointment (an appointment
     * with several payment rows — fee + settlement, partial + top-up —
     * lists once, with all its rows inside). Tabs pick appointments by the
     * payment states they hold: Pending = a pending non-cash payment (cash
     * needs no verification, as before), Verified / Rejected = any row in
     * that state, All = any payment at all. Cards sort by most recent
     * payment activity.
     */
    public function index(Request $request)
    {
        // Dropdown selections persist per session: a request WITH the param
        // uses it and stores it; without it the stored choice applies, then
        // the default. Search-style inputs are never persisted.
        $status = $request->query('status', $request->session()->get('filters.payments.status', PaymentStatus::Pending->value));
        if ($request->query('status') !== null) {
            $request->session()->put('filters.payments.status', $status);
        }
        $balance = $request->query('balance', $request->session()->get('filters.payments.balance', 'all'));
        if ($request->query('balance') !== null) {
            $request->session()->put('filters.payments.balance', $balance);
        }
        if (! in_array($balance, ['all', 'due', 'paid'], true)) {
            $balance = 'all';
        }

        $query = Appointment::with([
            'user', 'service', 'personnel',
            'payments' => fn ($query) => $query->orderBy('id'),
            'payments.verifier',
            // Latest-first approved cancellations for the card's refund
            // remark (see cancellationRefundRemark()) — avoids N+1.
            'changeRequests' => fn ($query) => $query
                ->where('type', AppointmentChangeRequest::TYPE_CANCELLATION)
                ->where('status', AppointmentChangeRequest::STATUS_APPROVED)
                ->latest('id'),
        ])
            ->select('appointments.*')
            ->selectSub(
                Payment::selectRaw('MAX(created_at)')->whereColumn('appointment_id', 'appointments.id'),
                'last_payment_at'
            )
            ->orderByDesc('last_payment_at')
            ->when($status === 'pending', fn ($query) => $query->whereHas(
                'payments',
                fn ($query) => $query->where('status', PaymentStatus::Pending->value)->where('method', '!=', 'cash')
            ))
            ->when($status === 'verified' || $status === 'rejected', fn ($query) => $query->whereHas(
                'payments',
                fn ($query) => $query->where('status', $status)
            ))
            ->when($status === 'all', fn ($query) => $query->whereHas('payments'))
            // Refund remarks as filters — same stored signals as
            // cancellationRefundRemark(): an approved cancellation request
            // plus verified money paid. A stored refund_decision wins;
            // decision-less (legacy) approvals fall back to the payments'
            // refund_status. 'refund' = refundable decision, or legacy
            // with a requested/sent refund row; 'no_refund' =
            // non-refundable decision, or legacy with no refund row.
            ->when($status === 'refund' || $status === 'no_refund', fn ($query) => $query
                ->whereHas('changeRequests', fn ($query) => $query
                    ->where('type', AppointmentChangeRequest::TYPE_CANCELLATION)
                    ->where('status', AppointmentChangeRequest::STATUS_APPROVED))
                ->whereHas('payments', fn ($query) => $query
                    ->where('status', PaymentStatus::Verified->value)))
            ->when($status === 'refund', fn ($query) => $query->where(function ($query) {
                $query->whereHas('changeRequests', fn ($query) => $query
                    ->where('type', AppointmentChangeRequest::TYPE_CANCELLATION)
                    ->where('status', AppointmentChangeRequest::STATUS_APPROVED)
                    ->where('refund_decision', 'refundable'))
                    ->orWhere(function ($query) {
                        $query->whereHas('changeRequests', fn ($query) => $query
                            ->where('type', AppointmentChangeRequest::TYPE_CANCELLATION)
                            ->where('status', AppointmentChangeRequest::STATUS_APPROVED)
                            ->whereNull('refund_decision'))
                            ->whereHas('payments', fn ($query) => $query
                                ->whereIn('refund_status', ['requested', 'sent']));
                    });
            }))
            ->when($status === 'no_refund', fn ($query) => $query->where(function ($query) {
                $query->whereHas('changeRequests', fn ($query) => $query
                    ->where('type', AppointmentChangeRequest::TYPE_CANCELLATION)
                    ->where('status', AppointmentChangeRequest::STATUS_APPROVED)
                    ->where('refund_decision', 'non_refundable'))
                    ->orWhere(function ($query) {
                        $query->whereHas('changeRequests', fn ($query) => $query
                            ->where('type', AppointmentChangeRequest::TYPE_CANCELLATION)
                            ->where('status', AppointmentChangeRequest::STATUS_APPROVED)
                            ->whereNull('refund_decision'))
                            ->whereDoesntHave('payments', fn ($query) => $query
                                ->whereIn('refund_status', ['requested', 'sent']));
                    });
            }));

        if ($balance === 'all') {
            $appointments = $query->paginate(6)->withQueryString();
        } else {
            // balanceDue() is derived from verified sums, not a column — so
            // the balance filter runs in PHP over the tab's rows, then
            // paginates the result manually (same page size, query kept).
            $filtered = $query->get()->filter(function (Appointment $appointment) use ($balance) {
                $due = $appointment->balanceDue();

                return $balance === 'due' ? $due > 0 : $due <= 0;
            })->values();

            $page = LengthAwarePaginator::resolveCurrentPage();
            $perPage = 6;
            $appointments = new LengthAwarePaginator(
                $filtered->slice(($page - 1) * $perPage, $perPage)->values(),
                $filtered->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        return view('admin.payments.index', [
            'appointments' => $appointments,
            'currentStatus' => $status,
            'currentBalance' => $balance,
            // Eligibility flags for the reject modal's toggles, keyed by
            // category — the modal JS only reads flags, never category keys.
            'rejectionFlags' => collect(config('payment.rejection_reasons', []))
                ->map(fn ($reason) => [
                    'topup_eligible' => (bool) ($reason['topup_eligible'] ?? false),
                ])
                ->all(),
        ]);
    }

    public function verify(Request $request, $id)
    {
        $payment = Payment::with('appointment')->findOrFail($id);

        $this->appointmentService->transition(
            $payment->appointment,
            AppointmentStatus::Booked,
            $request->user()
        );

        return back()->with('success', 'Payment verified — booking confirmed.');
    }

    public function reject(Request $request, $id)
    {
        $payment = Payment::with('appointment')->findOrFail($id);

        $reasons = config('payment.rejection_reasons', []);
        $flags = $reasons[$request->input('rejection_category')] ?? [];
        $needsAmount = ! empty($flags['topup_eligible']);

        $validated = $request->validate([
            'rejection_category' => ['required', Rule::in(array_keys($reasons))],
            // Custom text only exists for "Other" — excluded (ignored) for
            // every predefined reason, required when Other is picked.
            'rejection_reason' => 'exclude_unless:rejection_category,other|required|string|max:1000',
            // What the manager actually saw on the proof — only meaningful
            // for topup-eligible reasons (the top-up needs the held amount),
            // capped at what was charged.
            'amount_received' => $needsAmount
                ? ['required', 'numeric', 'min:0', 'max:'.$payment->amount]
                : ['nullable'],
        ]);

        $this->appointmentService->transition(
            $payment->appointment,
            AppointmentStatus::Cancelled,
            $request->user(),
            [
                'payment_id' => $payment->id,
                'rejection_category' => $validated['rejection_category'],
                'rejection_reason' => $validated['rejection_reason'] ?? null,
                'amount_received' => $validated['amount_received'] ?? null,
            ]
        );

        return back()->with('success', 'Payment rejected — booking cancelled.');
    }

    /**
     * Mark a requested refund as sent. Only a payment with a pending client
     * or cancellation-approval request qualifies (rejected booking-time
     * payments and verified payments held on an approved cancellation) —
     * everything re-checked here server-side. The client is notified by mail
     * that the money is on its way back. No appointment status moves.
     */
    public function markRefundSent(Request $request, $id, NotificationService $notificationService)
    {
        $payment = Payment::with(['appointment.user', 'appointment.service', 'appointment.payments'])->findOrFail($id);

        if (! in_array($payment->status, [PaymentStatus::Rejected, PaymentStatus::Verified], true)
            || $payment->refund_status !== 'requested') {
            return back()->withErrors(['refund' => 'This payment has no pending refund request.']);
        }

        $payment->update([
            'refund_status' => 'sent',
            'refund_sent_at' => now(),
            'refund_sent_by' => $request->user()->id,
        ]);

        if ($payment->appointment->user !== null) {
            $amount = number_format($payment->refundDueAmount(), 2);
            $notificationService->notify(
                $payment->appointment->user,
                'Refund sent',
                "Your ₱{$amount} refund for booking {$payment->appointment->referenceCode()} has been sent back.",
                new RefundSentMail($payment),
                ['appointment_id' => $payment->appointment->id]
            );
        }

        return back()->with('success', 'Refund marked as sent — the client has been notified.');
    }
}
