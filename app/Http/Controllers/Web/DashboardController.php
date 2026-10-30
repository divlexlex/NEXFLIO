<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Inventory;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\RevenueRecognitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __construct(
        private readonly RevenueRecognitionService $revenue,
    ) {
    }

    public function index(Request $request)
    {
        $today = now()->toDateString();
        $now = now();

        // Revenue cards (Rule A: verified payments by verified_at).
        // WTD = Monday→today, MTD = 1st→today, YTD = Jan 1→today.
        $wtdStart = $now->copy()->startOfWeek(Carbon::MONDAY);
        $mtdStart = $now->copy()->startOfMonth();
        $ytdStart = $now->copy()->startOfYear();
        $end = $now->copy()->endOfDay();

        // Top Services is owner-only (sha, 2026-10-29): managers never
        // get the section rendered, not merely hidden.
        $isOwner = auth()->user()?->role_id === User::ROLE_SUPER_ADMIN;

        $topPeriod = $request->query('top_period', 'mtd');
        if (! in_array($topPeriod, ['wtd', 'mtd', 'ytd'], true)) {
            $topPeriod = 'mtd';
        }

        $topServices = collect();
        $topNearMiss = collect();
        $topPeriodLabel = 'MTD · '.$now->format('F Y');
        // Entry thresholds are per period (sha, 2026-10-29).
        $topThreshold = match ($topPeriod) {
            'wtd' => 7,
            'mtd' => 21,
            'ytd' => 219,
            default => 21,
        };
        if ($isOwner) {
            [$topStart, $topPeriodLabel] = match ($topPeriod) {
                'wtd' => [$wtdStart, 'WTD · Week of '.$wtdStart->format('M j, Y')],
                'ytd' => [$ytdStart, 'YTD · '.$now->format('Y')],
                default => [$mtdStart, 'MTD · '.$now->format('F Y')],
            };
            [$topServices, $topNearMiss] = $this->topServices($topStart, $end, $topThreshold);
        }

        // Revenue overview custom date filter (Lyka, 2026-10-30): a
        // specific From–To window for peak-season tracking. It drives
        // ONLY the revenue overview (custom total + peak day + the
        // Weekly Revenue chart); the WTD/MTD/YTD cards and Top
        // Services above are never changed by it.
        $revFilter = $this->revenueFilterFromRequest($request);
        $revFilterTotal = null;
        $revPeak = null;
        $revFilterLabel = null;
        if ($revFilter['from'] !== null && $revFilter['to'] !== null) {
            $revFilterTotal = $this->revenue->sumBetween($revFilter['from'], $revFilter['to']);
            $daily = $this->revenue->dailyTotals($revFilter['from'], $revFilter['to']);
            // Earliest day wins a tie (daily buckets are date-ordered
            // and only a strictly larger total replaces the peak).
            $peakDay = null;
            foreach ($daily as $day) {
                if ($peakDay === null || $day['total'] > $peakDay['total']) {
                    $peakDay = $day;
                }
            }
            $revPeak = $revFilterTotal > 0 ? $peakDay : null;
            $revFilterLabel = $revFilter['from']->format('M j, Y').' – '.$revFilter['to']->format('M j, Y');
        }

        return view('admin.dashboard', [
            'todayBookings' => Appointment::whereDate('appointment_date', $today)->count(),
            'awaitingVerification' => Appointment::where('status', AppointmentStatus::Unverified)->count(),
            'upcomingBooked' => Appointment::where('appointment_date', '>=', $today)
                ->where('status', AppointmentStatus::Booked)
                ->count(),
            'monthRevenue' => $this->revenue->sumForMonth((int) now()->year, (int) now()->month),
            'lowStocks' => Inventory::whereColumn('quantity', '<=', 'reorder_point')->get(),
            'wtdRevenue' => $this->revenue->sumBetween($wtdStart, $end),
            'mtdRevenue' => $this->revenue->sumBetween($mtdStart, $end),
            'ytdRevenue' => $this->revenue->sumBetween($ytdStart, $end),
            'isOwner' => $isOwner,
            'topPeriod' => $topPeriod,
            'topPeriodLabel' => $topPeriodLabel,
            'topThreshold' => $topThreshold,
            'topServices' => $topServices,
            'topNearMiss' => $topNearMiss,
            // Revenue filter: $revFrom/$revTo are the validated
            // Y-m-d values (null when no valid filter) for the Top
            // Services pill links; $revFromInput/$revToInput are the
            // raw inputs so an invalid typed date stays visible.
            'revFrom' => $revFilter['from'] !== null ? $revFilter['from']->toDateString() : null,
            'revTo' => $revFilter['to'] !== null ? $revFilter['to']->toDateString() : null,
            'revFromInput' => $revFilter['fromInput'],
            'revToInput' => $revFilter['toInput'],
            'revFilterTotal' => $revFilterTotal,
            'revPeak' => $revPeak,
            'revFilterLabel' => $revFilterLabel,
            'revFilterError' => $revFilter['error'],
        ]);
    }

    /**
     * Parse the revenue overview custom date filter (rev_from/rev_to
     * query params). Returns null bounds with no error when neither
     * param is present, and null bounds with an error message when
     * the pair is incomplete or invalid — the dashboard then falls
     * back to its default Weekly Revenue view instead of breaking.
     *
     * @return array{from: ?Carbon, to: ?Carbon, error: ?string, fromInput: ?string, toInput: ?string}
     */
    private function revenueFilterFromRequest(Request $request): array
    {
        $fromInput = $request->query('rev_from');
        $toInput = $request->query('rev_to');
        $fromInput = is_string($fromInput) ? $fromInput : null;
        $toInput = is_string($toInput) ? $toInput : null;

        if ($fromInput === null && $toInput === null) {
            return ['from' => null, 'to' => null, 'error' => null, 'fromInput' => null, 'toInput' => null];
        }

        if ($fromInput === null || $fromInput === '' || $toInput === null || $toInput === '') {
            return ['from' => null, 'to' => null, 'error' => 'Select both a From date and a To date.', 'fromInput' => $fromInput, 'toInput' => $toInput];
        }

        try {
            $from = Carbon::createFromFormat('Y-m-d', $fromInput);
            $to = Carbon::createFromFormat('Y-m-d', $toInput);
            if ($from === false || $to === false || $from->format('Y-m-d') !== $fromInput || $to->format('Y-m-d') !== $toInput) {
                throw new \InvalidArgumentException('Invalid date.');
            }
        } catch (\Throwable) {
            return ['from' => null, 'to' => null, 'error' => 'Invalid date selected.', 'fromInput' => $fromInput, 'toInput' => $toInput];
        }

        if ($from->gt($to)) {
            return ['from' => null, 'to' => null, 'error' => 'From date must be on or before To date.', 'fromInput' => $fromInput, 'toInput' => $toInput];
        }

        return ['from' => $from->copy()->startOfDay(), 'to' => $to->copy()->endOfDay(), 'error' => null, 'fromInput' => $fromInput, 'toInput' => $toInput];
    }

    /**
     * Top Services: completed appointments performed in the period,
     * grouped by service. Entry thresholds are per period (sha,
     * 2026-10-29): WTD >= 7, MTD >= 21, YTD >= 219 completed services;
     * ranking is completed count desc with revenue as tie-breaker.
     * Revenue is the verified payments of those appointments. No
     * badges: inclusion in this table is itself the top-performer
     * signal (sha, 2026-10-29).
     *
     * @return array{0: Collection, 1: Collection} [qualifiers, near-miss]
     */
    private function topServices(Carbon $start, Carbon $end, int $threshold): array
    {
        $performed = Appointment::with(['service:id,name,category', 'payments'])
            ->where('status', AppointmentStatus::Completed)
            // appointment_date is a DATETIME column: comparing it to
            // date-only strings drops every appointment on the end date
            // in SQLite (the test DB), because '2026-10-28 00:00:00' is
            // lexicographically greater than '2026-10-28'. Compare full
            // datetimes instead, so the end date is fully included.
            ->whereBetween('appointment_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get();

        $grouped = $performed->groupBy(fn ($appointment) => $appointment->service->name ?? 'Unknown')
            ->map(function ($group) {
                $service = $group->first()->service;

                return [
                    'category' => $service->category ?? '',
                    'initials' => $this->serviceInitials($service->name ?? 'Unknown'),
                    'count' => $group->count(),
                    'revenue' => round((float) $group
                        ->flatMap(fn ($appointment) => $appointment->payments)
                        ->filter(fn ($payment) => $payment->status === PaymentStatus::Verified)
                        ->sum('amount'), 2),
                ];
            });

        $ranked = $grouped->sort(fn ($a, $b) => [$b['count'], $b['revenue']] <=> [$a['count'], $a['revenue']]);

        return [
            $ranked->filter(fn ($row) => $row['count'] >= $threshold),
            $ranked->filter(fn ($row) => $row['count'] > 0 && $row['count'] < $threshold)->take(5),
        ];
    }

    /**
     * Thumbnail placeholder initials for the Top Services table
     * (design v2, sha 2026-10-29): first letters of up to 3 words, or
     * the first 3 letters of a single-word name. No image column is
     * required, so this needs no migration.
     */
    private function serviceInitials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];

        if (count($words) > 1) {
            return strtoupper(implode('', array_map(
                fn ($word) => substr($word, 0, 1),
                array_slice($words, 0, 3)
            )));
        }

        return strtoupper(substr($name, 0, 3));
    }

    /**
     * Chart.js data — grouped in PHP so the queries stay portable across
     * MySQL and Azure SQL.
     */
    public function chartData(Request $request)
    {
        // Revenue chart: daily totals when a valid custom date filter
        // is in the URL (Lyka, 2026-10-30), otherwise revenue per
        // week, last 8 weeks (verified payments). An invalid filter
        // falls back to the weekly view — the page itself shows the
        // error. Bookings-by-service and staff charts below are NOT
        // filtered: this filter is revenue-only.
        $revFilter = $this->revenueFilterFromRequest($request);
        if ($revFilter['from'] !== null && $revFilter['to'] !== null) {
            $revenueByWeek = array_map(
                fn ($day) => ['label' => $day['label'], 'total' => $day['total']],
                $this->revenue->dailyTotals($revFilter['from'], $revFilter['to'])
            );
        } else {
            $revenueByWeek = $this->revenue->weeklyTotals(8);
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
