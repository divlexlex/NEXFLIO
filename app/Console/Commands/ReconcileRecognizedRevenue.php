<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\RevenueRecognitionService;
use Illuminate\Console\Command;

/**
 * Read-only reconciliation: surfaces rows where verified (collected) money and
 * recognized (completed-service) revenue diverge. Never mutates data — ops
 * review the report before any manual correction.
 */
class ReconcileRecognizedRevenue extends Command
{
    protected $signature = 'revenue:reconcile
                            {--year= : Limit to a calendar year (completed_at / verified_at)}';

    protected $description = 'Report verified payments vs completed-service recognized revenue (read-only)';

    public function handle(RevenueRecognitionService $revenue): int
    {
        $year = $this->option('year') ? (int) $this->option('year') : null;

        $recognized = $year
            ? $revenue->sumForYear($year)
            : $revenue->sumBetween();

        $verifiedQuery = Payment::query()->where('status', PaymentStatus::Verified);
        if ($year) {
            $verifiedQuery->whereYear('verified_at', $year);
        }
        $verifiedCollected = round((float) $verifiedQuery->sum('amount'), 2);

        $this->info('Recognized revenue (Completed appointments): ₱'.number_format($recognized, 2));
        $this->info('Verified payments collected (all statuses of booking): ₱'.number_format($verifiedCollected, 2));
        $this->newLine();

        $premature = Payment::query()
            ->where('status', PaymentStatus::Verified)
            ->whereHas('appointment', function ($query) {
                $query->where('status', '!=', AppointmentStatus::Completed->value);
            })
            ->with(['appointment:id,status,walk_in_name,user_id,booked_price,reference_code'])
            ->when($year, fn ($query) => $query->whereYear('verified_at', $year))
            ->orderBy('id')
            ->get();

        $this->line('Verified payments on non-completed appointments: '.$premature->count()
            .' (₱'.number_format((float) $premature->sum('amount'), 2).')');
        $this->line('These inflate old "verified payment" revenue but are correctly excluded from recognized revenue.');

        if ($premature->isNotEmpty()) {
            $this->table(
                ['Payment ID', 'Appointment', 'Status', 'Amount', 'Verified at'],
                $premature->take(50)->map(fn (Payment $payment) => [
                    $payment->id,
                    $payment->appointment?->reference_code ?? $payment->appointment_id,
                    $payment->appointment?->status?->value,
                    number_format((float) $payment->amount, 2),
                    optional($payment->verified_at)?->toDateTimeString(),
                ])
            );
            if ($premature->count() > 50) {
                $this->warn('Showing first 50 of '.$premature->count().' rows.');
            }
        }

        $missingStamp = Appointment::query()
            ->where('status', AppointmentStatus::Completed)
            ->whereNull('completed_at')
            ->count();

        if ($missingStamp > 0) {
            $this->warn("Completed appointments missing completed_at: {$missingStamp}. Re-run the completed_at migration backfill or set completed_at manually.");
        } else {
            $this->info('All Completed appointments have completed_at set.');
        }

        $this->newLine();
        $this->comment('No data was modified. Payment rows are preserved (collection ≠ recognition).');
        $this->comment('Cancelled / no-show / in-progress bookings contribute ₱0 recognized revenue automatically.');

        return self::SUCCESS;
    }
}
