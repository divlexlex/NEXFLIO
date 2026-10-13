<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Mail\RefundSentMail;
use App\Models\Payment;
use App\Services\AppointmentService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
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

    public function index(Request $request)
    {
        $status = $request->query('status', PaymentStatus::Pending->value);

        $payments = Payment::with(['appointment.user', 'appointment.service', 'appointment.personnel', 'verifier'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            // Cash bookings are confirmed at once and settle at the branch
            // (see AppointmentController@recordBranchPayment) — there is no
            // proof to verify, so they never sit in the pending queue.
            ->when($status === 'pending', fn ($query) => $query->where('method', '!=', 'cash'))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'currentStatus' => $status,
            // Eligibility flags for the reject modal's toggles, keyed by
            // category — the modal JS only reads flags, never category keys.
            'rejectionFlags' => collect(config('payment.rejection_reasons', []))
                ->map(fn ($reason) => [
                    'refund_eligible' => (bool) ($reason['refund_eligible'] ?? false),
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
        $needsAmount = ! empty($flags['refund_eligible']) || ! empty($flags['topup_eligible']);

        $validated = $request->validate([
            'rejection_category' => ['required', Rule::in(array_keys($reasons))],
            // Custom text only exists for "Other" — excluded (ignored) for
            // every predefined reason, required when Other is picked.
            'rejection_reason' => 'exclude_unless:rejection_category,other|required|string|max:1000',
            // What the manager actually saw on the proof — only meaningful
            // for refund/topup-eligible reasons, capped at what was charged.
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
     * Mark a requested refund as sent (step 2A). Only a rejected payment
     * with a pending client request qualifies — everything re-checked here
     * server-side. The client is notified by mail that the money is on its
     * way back. No appointment status moves; top-up is step 2B.
     */
    public function markRefundSent(Request $request, $id, NotificationService $notificationService)
    {
        $payment = Payment::with(['appointment.user', 'appointment.service'])->findOrFail($id);

        if ($payment->status !== PaymentStatus::Rejected || $payment->refund_status !== 'requested') {
            return back()->withErrors(['refund' => 'This payment has no pending refund request.']);
        }

        $payment->update([
            'refund_status' => 'sent',
            'refund_sent_at' => now(),
            'refund_sent_by' => $request->user()->id,
        ]);

        if ($payment->appointment->user !== null) {
            $amount = number_format((float) ($payment->amount_received ?? 0), 2);
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
