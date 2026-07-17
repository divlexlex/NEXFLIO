<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Commission;
use App\Models\Payment;

/**
 * Owner-only financial reports (role 1).
 */
class ReportController extends Controller
{
    public function financial()
    {
        // Twelve months of verified revenue, grouped in PHP for portability.
        $start = now()->startOfMonth()->subMonths(11);
        $payments = Payment::where('status', PaymentStatus::Verified)
            ->where('verified_at', '>=', $start)
            ->get(['amount', 'verified_at']);

        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $monthStart = $start->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();
            $months[] = [
                'label' => $monthStart->format('M Y'),
                'revenue' => round((float) $payments
                    ->filter(fn ($payment) => $payment->verified_at !== null
                        && $payment->verified_at->between($monthStart, $monthEnd))
                    ->sum('amount'), 2),
            ];
        }

        $completedThisYear = Appointment::with('service:id,name,price')
            ->where('status', AppointmentStatus::Completed)
            ->whereYear('appointment_date', now()->year)
            ->get();

        $topServices = $completedThisYear
            ->groupBy(fn ($appointment) => $appointment->service->name ?? 'Unknown')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'revenue' => round((float) $group->sum(fn ($a) => (float) ($a->service->price ?? 0)), 2),
            ])
            ->sortByDesc('revenue')
            ->take(10);

        return view('admin.reports.financial', [
            'months' => $months,
            'topServices' => $topServices,
            'yearRevenue' => round((float) Payment::where('status', PaymentStatus::Verified)
                ->whereYear('verified_at', now()->year)
                ->sum('amount'), 2),
            'yearCommissions' => round((float) Commission::whereYear('earned_at', now()->year)
                ->sum('amount'), 2),
        ]);
    }
}
