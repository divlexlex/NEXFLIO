<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill (step 2B): rejected payments with a topup_eligible
 * category and money recorded, rejected before partial receipts existed,
 * get their Verified partial_receipt row — amount, method, and proof
 * copied from the rejected row, verifier/timestamp copied from the
 * rejection when known. Idempotent: rows whose appointment already has a
 * partial receipt are skipped, so re-running creates no duplicates.
 */
class BackfillPartialReceipts extends Command
{
    protected $signature = 'payments:backfill-partials';

    protected $description = 'Create missing partial_receipt rows for pre-2B topup_eligible rejections';

    public function handle(): int
    {
        $eligible = collect(config('payment.rejection_reasons', []))
            ->filter(fn ($reason) => ! empty($reason['topup_eligible']))
            ->keys()
            ->all();

        if ($eligible === []) {
            $this->warn('No topup_eligible rejection categories configured — nothing to do.');

            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;

        $rejections = DB::table('payments')
            ->where('kind', 'payment')
            ->where('status', PaymentStatus::Rejected->value)
            ->whereIn('rejection_category', $eligible)
            ->where('amount_received', '>', 0)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->cursor();

        foreach ($rejections as $rejected) {
            $hasPartial = DB::table('payments')
                ->where('appointment_id', $rejected->appointment_id)
                ->where('kind', 'partial_receipt')
                ->whereNull('deleted_at')
                ->exists();

            if ($hasPartial) {
                $skipped++;
                continue;
            }

            DB::table('payments')->insert([
                'appointment_id' => $rejected->appointment_id,
                'kind' => 'partial_receipt',
                'amount' => $rejected->amount_received,
                'method' => $rejected->method,
                'proof_path' => $rejected->proof_path,
                'status' => PaymentStatus::Verified->value,
                'verified_by' => $rejected->verified_by,
                'verified_at' => $rejected->verified_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created++;
        }

        $this->info("Created {$created} partial receipt(s), skipped {$skipped} already-covered payment(s).");

        return self::SUCCESS;
    }
}
