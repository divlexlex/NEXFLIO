<?php

namespace App\Http\Controllers\Web;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\RevenueRecognitionService;
use App\Services\WeeklyPayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BillingController extends Controller
{
    public function __construct(
        private readonly RevenueRecognitionService $revenue,
        private readonly WeeklyPayrollService $payroll,
    ) {
    }

    public function index(Request $request)
    {
        $month = $request->query('month', now()->format('Y-m'));
        [$year, $mon] = array_pad(explode('-', $month), 2, null);
        $year = (int) $year;
        $mon = (int) $mon;

        // Staff payroll is WEEKLY (2026-10-24): daily base pay × days
        // attended that week + that week's commissions, computed by
        // WeeklyPayrollService. ?week= accepts any date inside the wanted
        // week (Sunday–Saturday) and is normalized to its Sunday; an
        // invalid or missing value falls back to the current week.
        $weekParam = $request->query('week');
        $weekDate = now()->toDateString();
        if (is_string($weekParam) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekParam)) {
            try {
                $weekDate = Carbon::createFromFormat('Y-m-d', $weekParam)->toDateString();
            } catch (\Throwable $e) {
                $weekDate = now()->toDateString();
            }
        }
        [$weekStart, $weekEnd] = $this->payroll->weekRange($weekDate);
        $staff = $this->payroll->forAllStaff($weekStart);

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
            'week' => $weekStart,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'staffPayroll' => $staff,
            'payments' => $payments,
            'revenueTotal' => $this->revenue->sumForMonth($year, $mon),
            'collectedTotal' => round((float) $payments->sum('amount'), 2),
            'payrollTotal' => round((float) $staff->sum('total'), 2),
        ]);
    }
}
