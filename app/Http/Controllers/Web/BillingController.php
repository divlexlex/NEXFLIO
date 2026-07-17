<?php

namespace App\Http\Controllers\Web;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->query('month', now()->format('Y-m'));
        [$year, $mon] = array_pad(explode('-', $month), 2, null);

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
            'revenueTotal' => round((float) $payments->sum('amount'), 2),
            'payrollTotal' => round((float) $staff->sum('total'), 2),
        ]);
    }
}
