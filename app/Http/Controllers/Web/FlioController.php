<?php

namespace App\Http\Controllers\Web;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Promo;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\RevenueRecognitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Flio — the admin-portal floating chat assistant. Answers Owner/Manager
 * questions from a freshly-built snapshot of system aggregates and hands it
 * to GeminiService::answerManagerQuestion(). The snapshot is aggregates only
 * (totals, counts, percentages, service/category/staff/item/promo names) —
 * never client names, emails, phones, addresses or per-client rows.
 *
 * Nothing is persisted: the snapshot is computed per request and the
 * conversation lives only in the browser and the Gemini call.
 */
class FlioController extends Controller
{
    public function __construct(
        private readonly RevenueRecognitionService $revenue,
    ) {
    }

    public function ask(Request $request, GeminiService $gemini)
    {
        // Validated manually so we can always answer with JSON. The app only
        // renders JSON error responses for api/* (see bootstrap/app.php), so a
        // plain $request->validate() here would redirect instead of returning 422.
        $validator = Validator::make($request->all(), [
            'question' => ['required', 'string', 'max:500'],
            'history' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $validated = $validator->validated();

        if (! $gemini->isConfigured()) {
            return response()->json(['answer' => null, 'configured' => false]);
        }

        // Normalize to role/text, accept only user/assistant roles, and keep
        // just the last 6 turns — the service enforces the cap again too.
        $history = [];
        foreach (array_slice($validated['history'] ?? [], -6) as $turn) {
            if (! is_array($turn) || ! in_array($turn['role'] ?? null, ['user', 'assistant'], true)) {
                continue;
            }

            $text = trim((string) ($turn['text'] ?? ''));
            if ($text !== '') {
                $history[] = ['role' => $turn['role'], 'text' => $text];
            }
        }

        $answer = $gemini->answerManagerQuestion($validated['question'], $this->contextSnapshot(), $history);

        return response()->json(['answer' => $answer, 'configured' => true]);
    }

    /**
     * Builds the aggregate-only DATA snapshot for one question. The money and
     * demand numbers reuse the exact same computations as the dashboard's
     * weeklyMetrics() so Flio and the insight card never disagree.
     *
     * @return array<string, mixed>
     */
    private function contextSnapshot(): array
    {
        $weekStart = now()->startOfWeek();
        $prevWeekStart = $weekStart->copy()->subWeek();
        $today = now()->toDateString();

        $todayAppointments = Appointment::whereDate('appointment_date', $today)->get();

        $recent = Appointment::with('service:id,name')
            ->where('appointment_date', '>=', now()->subDays(30))
            ->get();
        $last90 = Appointment::where('appointment_date', '>=', now()->subDays(90))->get();

        // Money — same math as DashboardController::weeklyMetrics()
        // (verified payments, Rule A — same as the dashboard).
        $revenueThisWeek = $this->revenue->sumBetween($weekStart);
        $revenueLastWeek = $this->revenue->sumBetween($prevWeekStart, $weekStart->copy()->subSecond());

        $revenueByCategory = $this->revenue->byCategorySince(now()->subDays(30));

        // Demand — same math as weeklyMetrics().
        $byDay = $last90
            ->groupBy(fn (Appointment $appointment) => $appointment->appointment_date->format('l'))
            ->map->count()
            ->sortDesc();

        $byHour = $last90
            ->groupBy(fn (Appointment $appointment) => (int) substr((string) $appointment->start_time, 0, 2))
            ->map->count()
            ->sortDesc()
            ->take(3)
            ->map(fn ($count, $hour) => [
                'time' => Carbon::createFromTime((int) $hour, 0)->format('g:i A'),
                'bookings' => $count,
            ])
            ->values();

        $completedByClient = $last90
            ->where('status', AppointmentStatus::Completed)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->map->count();
        $repeatClientRate = $completedByClient->isNotEmpty()
            ? round($completedByClient->filter(fn ($count) => $count >= 2)->count() / $completedByClient->count() * 100, 1)
            : 0.0;

        $nonCancelled = $recent->reject(fn (Appointment $appointment) => $appointment->status === AppointmentStatus::Cancelled);
        $averageBookingValue = $nonCancelled->isNotEmpty()
            ? round((float) $nonCancelled->avg(fn (Appointment $appointment) => (float) $appointment->booked_price), 2)
            : 0.0;
        $cancellationRate = $recent->isNotEmpty()
            ? round($recent->where('status', AppointmentStatus::Cancelled)->count() / $recent->count() * 100, 1)
            : 0.0;

        // Staff workload this week: completed appointments per active staff.
        $completedThisWeekByStaff = Appointment::where('status', AppointmentStatus::Completed)
            ->where('appointment_date', '>=', $weekStart)
            ->whereNotNull('personnel_id')
            ->get()
            ->groupBy('personnel_id')
            ->map->count();

        $staffWorkload = User::where('role_id', User::ROLE_STAFF)
            ->whereHas('staffProfile', fn ($query) => $query->where('employment_status', 'active'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $staff) => [
                'name' => $staff->name,
                'completed_appointments' => (int) ($completedThisWeekByStaff[$staff->id] ?? 0),
            ])
            ->values();

        // Low stock: on-hand is the batch ledger (sum of remaining FIFO
        // quantities), exactly like the Inventory page.
        $lowStock = Inventory::withSum('batches', 'quantity_remaining')
            ->get()
            ->filter(fn (Inventory $item) => (int) ($item->batches_sum_quantity_remaining ?? 0) <= (int) $item->reorder_point)
            ->map(fn (Inventory $item) => [
                'item' => $item->item_name,
                'on_hand' => (int) ($item->batches_sum_quantity_remaining ?? 0),
                'reorder_point' => (int) $item->reorder_point,
            ])
            ->values();

        return [
            'today' => [
                'date' => $today,
                'appointments' => $todayAppointments->count(),
                'completed' => $todayAppointments->where('status', AppointmentStatus::Completed)->count(),
            ],
            'pending_work' => [
                'payments_awaiting_verification' => Payment::where('status', PaymentStatus::Pending)->count(),
                'pending_change_requests' => AppointmentChangeRequest::where('status', AppointmentChangeRequest::STATUS_PENDING)->count(),
            ],
            'money' => [
                'currency' => 'PHP',
                'revenue_this_week' => round($revenueThisWeek, 2),
                'revenue_last_week' => round($revenueLastWeek, 2),
                'revenue_by_category_last_30_days' => $revenueByCategory,
            ],
            'demand' => [
                'bookings_last_30_days' => $recent->count(),
                'peak_day' => $byDay->keys()->first(),
                'busiest_start_hours_last_90_days' => $byHour,
                'top_services_last_30_days' => $recent
                    ->groupBy(fn (Appointment $appointment) => $appointment->service->name ?? 'Unknown')
                    ->map->count()
                    ->sortDesc()
                    ->take(5),
                'average_booking_value_last_30_days' => $averageBookingValue,
                'cancellation_rate_last_30_days' => $cancellationRate,
                'repeat_client_rate_last_90_days' => $repeatClientRate,
            ],
            'staff_workload_this_week' => $staffWorkload,
            'low_stock' => $lowStock,
            'active_promos' => Promo::live()->orderBy('title')->pluck('title')->values(),
        ];
    }
}
