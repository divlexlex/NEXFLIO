<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\AppointmentService;
use Illuminate\Http\Request;

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
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'currentStatus' => $status,
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
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $payment = Payment::with('appointment')->findOrFail($id);

        $this->appointmentService->transition(
            $payment->appointment,
            AppointmentStatus::Cancelled,
            $request->user(),
            ['rejection_reason' => $validated['rejection_reason']]
        );

        return back()->with('success', 'Payment rejected — booking cancelled.');
    }
}
