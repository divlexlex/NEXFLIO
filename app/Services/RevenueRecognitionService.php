<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rule A (sha, 2026-10-19): revenue is VERIFIED money, dated by verified_at.
 *
 * A down payment counts the moment a manager verifies it — including a
 * second-chance payment on a booking whose first payment was rejected
 * (top-up after insufficient_amount, resubmitted proof after
 * invalid_proof): the verify step flips those rows to Verified, so the
 * credit is automatic and happens exactly once per payment row.
 *
 * Two guards keep the total honest:
 *  - a payment on a Cancelled appointment counts ₱0 (a rejected booking's
 *    held partial receipt is not revenue while the booking is dead; if the
 *    client revives it with a top-up, the appointment leaves Cancelled and
 *    the held money counts again);
 *  - completing an appointment adds nothing — the balance was already
 *    credited when its branch settlement / check-out payment was recorded
 *    as Verified, so a booking's revenue can never exceed what was paid.
 */
class RevenueRecognitionService
{
    /**
     * Verified money collected for one appointment. ₱0 while the
     * appointment is Cancelled, whatever its payment rows say.
     */
    public function amountFor(Appointment $appointment): float
    {
        if ($appointment->status === AppointmentStatus::Cancelled) {
            return 0.0;
        }

        return round((float) $appointment->verifiedPayments()->sum('amount'), 2);
    }

    /**
     * Verified payments in the inclusive window, excluding payments whose
     * appointment is Cancelled.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Payment>
     */
    public function verifiedQuery(?Carbon $from = null, ?Carbon $to = null)
    {
        $query = Payment::query()
            ->where('status', PaymentStatus::Verified)
            ->whereHas('appointment', fn ($query) => $query
                ->where('status', '!=', AppointmentStatus::Cancelled->value));

        if ($from !== null) {
            $query->where('verified_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('verified_at', '<=', $to);
        }

        return $query;
    }

    public function sumBetween(?Carbon $from = null, ?Carbon $to = null): float
    {
        return round((float) $this->verifiedQuery($from, $to)->sum('amount'), 2);
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
        $payments = $this->verifiedQuery($start)->get(['amount', 'verified_at']);

        $buckets = [];
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $weekEnd = $weekStart->copy()->endOfWeek();
            $slice = $payments->filter(
                fn (Payment $payment) => $payment->verified_at !== null
                    && $payment->verified_at->between($weekStart, $weekEnd)
            );

            $buckets[] = [
                'label' => $weekStart->format('M j'),
                'total' => round((float) $slice->sum('amount'), 2),
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
        $payments = $this->verifiedQuery($start)->get(['amount', 'verified_at']);

        $buckets = [];
        for ($i = 0; $i < $months; $i++) {
            $monthStart = $start->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();
            $slice = $payments->filter(
                fn (Payment $payment) => $payment->verified_at !== null
                    && $payment->verified_at->between($monthStart, $monthEnd)
            );

            $buckets[] = [
                'label' => $monthStart->format('M Y'),
                'revenue' => round((float) $slice->sum('amount'), 2),
            ];
        }

        return $buckets;
    }

    /**
     * Verified revenue rolled up by service category since $from.
     *
     * @return Collection<string, float>
     */
    public function byCategorySince(Carbon $from): Collection
    {
        return $this->verifiedQuery($from)
            ->with('appointment.service:id,category')
            ->get()
            ->groupBy(fn (Payment $payment) => $payment->appointment->service->category ?? 'Unknown')
            ->map(fn (Collection $group) => round((float) $group->sum('amount'), 2))
            ->sortDesc();
    }
}
