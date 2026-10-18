<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Completed-service revenue only. Payment verification records cash/collection
 * facts and must not inflate recognized revenue before the appointment reaches
 * {@see AppointmentStatus::Completed}.
 *
 * Amount = booked_price snapshot, falling back to the service's listed price.
 * Period filters use completed_at (set once at completion).
 */
class RevenueRecognitionService
{
    /**
     * Service value that counts as recognized revenue for one appointment.
     * Zero unless the appointment is Completed.
     */
    public function amountFor(Appointment $appointment): float
    {
        if ($appointment->status !== AppointmentStatus::Completed) {
            return 0.0;
        }

        $appointment->loadMissing('service:id,price');

        return round((float) ($appointment->booked_price ?? $appointment->service?->price ?? 0), 2);
    }

    /**
     * Completed appointments with a recognition timestamp in the inclusive window.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Appointment>
     */
    public function completedQuery(?Carbon $from = null, ?Carbon $to = null)
    {
        $query = Appointment::query()
            ->where('status', AppointmentStatus::Completed)
            ->whereNotNull('completed_at');

        if ($from !== null) {
            $query->where('completed_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('completed_at', '<=', $to);
        }

        return $query;
    }

    public function sumBetween(?Carbon $from = null, ?Carbon $to = null): float
    {
        return $this->sumAppointments(
            $this->completedQuery($from, $to)->with('service:id,price')->get()
        );
    }

    public function sumForMonth(int $year, int $month): float
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return $this->sumBetween($start, $end);
    }

    public function sumForYear(int $year): float
    {
        $start = Carbon::create($year, 1, 1)->startOfYear();
        $end = $start->copy()->endOfYear();

        return $this->sumBetween($start, $end);
    }

    /**
     * Last N calendar weeks ending with the current week. Each bucket is
     * [label, total] keyed for chart consumers.
     *
     * @return list<array{label: string, total: float}>
     */
    public function weeklyTotals(int $weeks = 8): array
    {
        $start = now()->startOfWeek()->subWeeks($weeks - 1);
        $appointments = $this->completedQuery($start)
            ->with('service:id,price')
            ->get(['id', 'booked_price', 'service_id', 'completed_at', 'status']);

        $buckets = [];
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $weekEnd = $weekStart->copy()->endOfWeek();
            $slice = $appointments->filter(
                fn (Appointment $appointment) => $appointment->completed_at !== null
                    && $appointment->completed_at->between($weekStart, $weekEnd)
            );

            $buckets[] = [
                'label' => $weekStart->format('M j'),
                'total' => $this->sumAppointments($slice),
            ];
        }

        return $buckets;
    }

    /**
     * Last N calendar months ending with the current month.
     *
     * @return list<array{label: string, revenue: float}>
     */
    public function monthlyTotals(int $months = 12): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $appointments = $this->completedQuery($start)
            ->with('service:id,price')
            ->get(['id', 'booked_price', 'service_id', 'completed_at', 'status']);

        $buckets = [];
        for ($i = 0; $i < $months; $i++) {
            $monthStart = $start->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();
            $slice = $appointments->filter(
                fn (Appointment $appointment) => $appointment->completed_at !== null
                    && $appointment->completed_at->between($monthStart, $monthEnd)
            );

            $buckets[] = [
                'label' => $monthStart->format('M Y'),
                'revenue' => $this->sumAppointments($slice),
            ];
        }

        return $buckets;
    }

    /**
     * Recognized revenue rolled up by service category since $from.
     *
     * @return Collection<string, float>
     */
    public function byCategorySince(Carbon $from): Collection
    {
        return $this->completedQuery($from)
            ->with('service:id,category,price')
            ->get()
            ->groupBy(fn (Appointment $appointment) => $appointment->service->category ?? 'Unknown')
            ->map(fn (Collection $group) => $this->sumAppointments($group))
            ->sortDesc();
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     */
    private function sumAppointments(Collection $appointments): float
    {
        return round((float) $appointments->sum(
            fn (Appointment $appointment) => (float) ($appointment->booked_price ?? $appointment->service?->price ?? 0)
        ), 2);
    }
}
