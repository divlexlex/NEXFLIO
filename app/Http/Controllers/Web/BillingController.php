<?php

namespace App\Http\Controllers\Web;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Payment;
use App\Models\User;
use App\Services\RevenueRecognitionService;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(
        private readonly RevenueRecognitionService $revenue,
    ) {
    }

    public function index(Request $request)
    {
        $month = $request->query('month', now()->format('Y-m'));
        [$year, $mon] = array_pad(explode('-', $month), 2, null);
        $year = (int) $year;
        $mon = (int) $mon;

        // Per-staff payroll for the month: fixed base + commission ledger sum.
        $staff = User::where('role_id', User::ROLE_STAFF)
            ->with('staffProfile')
            ->orderBy('name')
            ->get()
            ->map(function (User $member) use ($year, $mon) {
                $commission = (float) Commission::where('user_id', $member->id)
                    ->whereYear('earned_at', $year)
                    ->whereMonth('earned_at', $mon)
                    ->sum('amount');
                $basePay = (float) ($member->staffProfile?->base_pay ?? 0);

                return [
                    'user' => $member,
                    'base_pay' => $basePay,
                    'commission' => round($commission, 2),
                    'total' => round($basePay + $commission, 2),
                ];
            });

        // Collection ledger for the month (verified payments) — separate from
        // revenue (Rule A counts these same verified payments, minus any on Cancelled appointments).
        $payments = Payment::with(['appointment.service', 'appointment.user', 'verifier'])
            ->where('status', PaymentStatus::Verified)
            ->whereYear('verified_at', $year)
            ->whereMonth('verified_at', $mon)
            ->orderByDesc('verified_at')
            ->get();

        return view('admin.billing.index', [
            'month' => $month,
            'staffPayroll' => $staff,
            'payments' => $payments,
            'revenueTotal' => $this->revenue->sumForMonth($year, $mon),
            'collectedTotal' => round((float) $payments->sum('amount'), 2),
            'payrollTotal' => round((float) $staff->sum('total'), 2),
        ]);
    }
}
