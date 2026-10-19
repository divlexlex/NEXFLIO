<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Inventory;
use App\Services\GeminiService;
use App\Services\RevenueRecognitionService;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __construct(
        private readonly RevenueRecognitionService $revenue,
    ) {
    }

    public function index()
    {
        $today = now()->toDateString();

        return view('admin.dashboard', [
            'todayBookings' => Appointment::whereDate('appointment_date', $today)->count(),
            'awaitingVerification' => Appointment::where('status', AppointmentStatus::Unverified)->count(),
            'upcomingBooked' => Appointment::where('appointment_date', '>=', $today)
                ->where('status', AppointmentStatus::Booked)
                ->count(),
            'monthRevenue' => $this->revenue->sumForMonth((int) now()->year, (int) now()->month),
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
        $revenueByWeek = $this->revenue->weeklyTotals(8);

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
            'insights:week:'.now()->format('o-W'),
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

        $revenueThisWeek = $this->revenue->sumBetween($weekStart);
        $revenueLastWeek = $this->revenue->sumBetween($prevWeekStart, $weekStart->copy()->subSecond());

        $recent = Appointment::with('service:id,name')
            ->where('appointment_date', '>=', now()->subDays(30))
            ->get();

        // Verified money over the last 30 days rolled up by service category.
        $revenueByCategory = $this->revenue->byCategorySince(now()->subDays(30));

        // 90-day booking rhythm: per weekday and per start hour.
        $last90 = Appointment::where('appointment_date', '>=', now()->subDays(90))->get();

        $byDay = $last90
            ->groupBy(fn (Appointment $appointment) => $appointment->appointment_date->format('l'))
            ->map->count()
            ->sortDesc();

        $byHour = $last90
            ->groupBy(fn (Appointment $appointment) => (int) substr((string) $appointment->start_time, 0, 2))
            ->map->count()
            ->sortDesc()
            ->take(3)
            ->map(fn ($count, $hour) => ['hour' => (int) $hour, 'count' => $count])
            ->values();

        // Repeat rate: among registered clients with a completed visit in the
        // window, the share who completed two or more.
        $completedByClient = $last90
            ->where('status', AppointmentStatus::Completed)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->map->count();

        $repeatClients = $completedByClient->filter(fn ($count) => $count >= 2)->count();
        $repeatClientRate = $completedByClient->isNotEmpty()
            ? round($repeatClients / $completedByClient->count() * 100, 1)
            : 0.0;

        $nonCancelled = $recent->reject(fn (Appointment $appointment) => $appointment->status === AppointmentStatus::Cancelled);
        $averageBookingValue = $nonCancelled->isNotEmpty()
            ? round((float) $nonCancelled->avg(fn (Appointment $appointment) => (float) $appointment->booked_price), 2)
            : 0.0;
        $cancellationRate = $recent->isNotEmpty()
            ? round($recent->where('status', AppointmentStatus::Cancelled)->count() / $recent->count() * 100, 1)
            : 0.0;

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
            'revenue_by_category_last_30_days' => $revenueByCategory,
            'bookings_by_day_of_week_last_90_days' => $byDay,
            'peak_day' => $byDay->keys()->first(),
            'busiest_start_hours_last_90_days' => $byHour,
            'repeat_client_rate_last_90_days' => $repeatClientRate,
            'average_booking_value_last_30_days' => $averageBookingValue,
            'cancellation_rate_last_30_days' => $cancellationRate,
            // On-hand stock is the sum of each item's remaining FIFO batches,
            // never the denormalized inventories.quantity cache — computed the
            // same way the Inventory page surfaces on-hand. Item names only.
            'low_stock_items' => Inventory::withSum('batches', 'quantity_remaining')
                ->get()
                ->filter(fn (Inventory $item) => (int) ($item->batches_sum_quantity_remaining ?? 0) <= (int) $item->reorder_point)
                ->pluck('item_name')
                ->values(),
        ];
    }
}
