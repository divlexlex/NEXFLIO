<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Inventory;
use App\Models\Payment;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        return view('admin.dashboard', [
            'todayBookings' => Appointment::whereDate('appointment_date', $today)->count(),
            'awaitingVerification' => Appointment::where('status', AppointmentStatus::Unverified)->count(),
            'upcomingBooked' => Appointment::where('appointment_date', '>=', $today)
                ->where('status', AppointmentStatus::Booked)
                ->count(),
            'monthRevenue' => (float) Payment::where('status', PaymentStatus::Verified)
                ->whereYear('verified_at', now()->year)
                ->whereMonth('verified_at', now()->month)
                ->sum('amount'),
            'lowStocks' => Inventory::whereColumn('quantity', '<=', 'reorder_point')->get(),
        ]);
    }

    /**
     * Chart.js data — grouped in PHP so the queries stay portable across
     * MySQL and Azure SQL.
     */
    public function chartData()
    {
        // Revenue per week, last 8 weeks (verified payments).
        $start = now()->startOfWeek()->subWeeks(7);
        $payments = Payment::where('status', PaymentStatus::Verified)
            ->where('verified_at', '>=', $start)
            ->get(['amount', 'verified_at']);

        $revenueByWeek = [];
        for ($i = 0; $i < 8; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $weekEnd = $weekStart->copy()->endOfWeek();
            $revenueByWeek[] = [
                'label' => $weekStart->format('M j'),
                'total' => round((float) $payments
                    ->filter(fn ($payment) => $payment->verified_at !== null
                        && $payment->verified_at->between($weekStart, $weekEnd))
                    ->sum('amount'), 2),
            ];
        }

        // Bookings per service and completions per staff, last 30 days.
        $recent = Appointment::with(['service:id,name', 'personnel:id,name'])
            ->where('appointment_date', '>=', now()->subDays(30))
            ->get();

        $byService = $recent
            ->groupBy(fn ($appointment) => $appointment->service->name ?? 'Unknown')
            ->map->count()
            ->sortDesc()
            ->take(8);

        $byStaff = $recent
            ->where('status', AppointmentStatus::Completed)
            ->groupBy(fn ($appointment) => $appointment->personnel->name ?? 'Unknown')
            ->map->count()
            ->sortDesc();

        return response()->json([
            'revenue_weeks' => array_column($revenueByWeek, 'label'),
            'revenue_totals' => array_column($revenueByWeek, 'total'),
            'service_labels' => $byService->keys()->values(),
            'service_counts' => $byService->values(),
            'staff_labels' => $byStaff->keys()->values(),
            'staff_counts' => $byStaff->values(),
        ]);
    }

    /**
     * AI-summarized weekly insight, cached for 12 hours. Returns null when
     * Gemini is unconfigured or unreachable — the card degrades, the
     * dashboard never breaks.
     */
    public function insights(GeminiService $gemini)
    {
        if (! $gemini->isConfigured()) {
            return response()->json(['insight' => null, 'configured' => false]);
        }

        $insight = Cache::remember(
            'insights:week:' . now()->format('o-W'),
            now()->addHours(12),
            fn () => $gemini->summarizeOperations($this->weeklyMetrics())
        );

        return response()->json(['insight' => $insight, 'configured' => true]);
    }

    /**
     * @return array<string, mixed> aggregated numbers only — no client PII
     */
    private function weeklyMetrics(): array
    {
        $weekStart = now()->startOfWeek();
        $prevWeekStart = $weekStart->copy()->subWeek();

        $revenueThisWeek = (float) Payment::where('status', PaymentStatus::Verified)
            ->where('verified_at', '>=', $weekStart)->sum('amount');
        $revenueLastWeek = (float) Payment::where('status', PaymentStatus::Verified)
            ->whereBetween('verified_at', [$prevWeekStart, $weekStart])->sum('amount');

        $recent = Appointment::with('service:id,name')
            ->where('appointment_date', '>=', now()->subDays(30))
            ->get();

        return [
            'currency' => 'PHP',
            'revenue_this_week' => round($revenueThisWeek, 2),
            'revenue_last_week' => round($revenueLastWeek, 2),
            'bookings_last_30_days' => $recent->count(),
            'completed_last_30_days' => $recent->where('status', AppointmentStatus::Completed)->count(),
            'cancellations_last_30_days' => $recent->where('status', AppointmentStatus::Cancelled)->count(),
            'no_shows_last_30_days' => $recent->where('status', AppointmentStatus::NoShow)->count(),
            'awaiting_verification' => Appointment::where('status', AppointmentStatus::Unverified)->count(),
            'top_services_last_30_days' => $recent
                ->groupBy(fn ($appointment) => $appointment->service->name ?? 'Unknown')
                ->map->count()
                ->sortDesc()
                ->take(5),
            'low_stock_items' => Inventory::whereColumn('quantity', '<=', 'reorder_point')
                ->pluck('item_name'),
        ];
    }
}
