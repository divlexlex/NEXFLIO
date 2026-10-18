<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;

/**
 * Digital transaction record ("receipt") for Completed appointments —
 * client and admin views rendering one shared Blade view. Read-only:
 * everything derives from the appointment's Verified payments via the
 * existing Appointment helpers; nothing is written here.
 */
class ReceiptController extends Controller
{
    /** Client view — own appointments only (others 404). */
    public function client(Request $request, $id)
    {
        $appointment = Appointment::with(['service', 'payments', 'addedServices.service'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($appointment->status !== AppointmentStatus::Completed) {
            return redirect()->route('account.bookings')->withErrors([
                'receipt' => 'Receipts are available for completed appointments only.',
            ]);
        }

        return $this->render($appointment, route('account.bookings'));
    }

    /** Admin/Manager view — any appointment (admin role middleware applies). */
    public function admin(Request $request, $id)
    {
        $appointment = Appointment::with(['service', 'payments', 'addedServices.service'])->findOrFail($id);

        if ($appointment->status !== AppointmentStatus::Completed) {
            return back()->withErrors([
                'receipt' => 'Receipts are available for completed appointments only.',
            ]);
        }

        return $this->render($appointment, route('admin.payments'));
    }

    private function render(Appointment $appointment, string $backUrl)
    {
        $verified = $appointment->payments->filter(
            fn ($payment) => $payment->status === PaymentStatus::Verified
        );

        return view('receipts.show', [
            'appointment' => $appointment,
            'downPayment' => $this->paymentLine($verified->filter(
                fn ($payment) => $payment->kind !== 'branch'
            )),
            'branchPaid' => $this->paymentLine($verified->filter(
                fn ($payment) => $payment->kind === 'branch'
            )),
            'total' => (float) $appointment->price,
            'backUrl' => $backUrl,
        ]);
    }

    /**
     * One aggregated receipt line: summed amount + " — {method}" only when
     * every payment in the group shares a single method. Null when empty
     * (the line is omitted).
     *
     * @return array{amount: float, method: string|null}|null
     */
    private function paymentLine($payments): ?array
    {
        if ($payments->isEmpty()) {
            return null;
        }

        $methods = $payments->pluck('method')->filter()->unique()->values();

        return [
            'amount' => (float) $payments->sum('amount'),
            'method' => $methods->count() === 1
                ? config('payment.methods.'.$methods->first(), $methods->first())
                : null,
        ];
    }
}
