<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Commission;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off fresh start for LOCAL appointments data: backs the database up,
 * then wipes every appointment (plus its payments, addresses, change
 * requests via DB cascades, and commissions first since that FK has no
 * cascade). USER ACCOUNTS ARE NEVER TOUCHED — no statement in this command
 * writes to the users table.
 *
 * --dry-run: backup + preview figures only, delete nothing.
 * --no-backup: skip the mysqldump step (automated tests only — they run on
 *   sqlite, where mysqldump cannot work).
 * Refuses to run when APP_ENV=production.
 */
class FreshStartAppointments extends Command
{
    protected $signature = 'data:fresh-start-appointments
        {--dry-run : Back up and print preview figures without deleting anything}
        {--no-backup : Skip the mysqldump backup (automated tests only)}';

    protected $description = 'Back up the local database, then wipe all appointments, payments, and commissions (users untouched)';

    public function handle(): int
    {
        if ($this->laravel->environment('production')) {
            $this->error('Refusing to run data:fresh-start-appointments in production.');

            return self::FAILURE;
        }

        if (! $this->option('no-backup')) {
            $backupPath = $this->createBackup();

            if ($backupPath === null) {
                return self::FAILURE;
            }

            $this->info('Backup written to '.$backupPath);
        }

        $this->printFigures('Before');

        if ($this->option('dry-run')) {
            $this->info('Dry run — nothing deleted.');

            return self::SUCCESS;
        }

        DB::transaction(function () {
            // Query-builder deletes: hard deletes, no model events, no audit
            // spam. Commissions first (their appointment FK has no cascade);
            // appointments cascade to payments, addresses, change requests.
            DB::table('commissions')->delete();
            DB::table('appointments')->delete();
        });

        $this->printFigures('After');

        return self::SUCCESS;
    }

    /**
     * Dump the whole database with mysqldump. The password travels in the
     * MYSQL_PWD environment variable — it never appears in the command
     * line and is never printed. Returns the backup path, or null when the
     * dump failed (nothing else runs in that case).
     */
    private function createBackup(): ?string
    {
        $connection = config('database.connections.'.config('database.default'));

        if (($connection['driver'] ?? null) !== 'mysql') {
            $this->error('Backup supports the mysql connection only (current driver: '.($connection['driver'] ?? 'unknown').').');

            return null;
        }

        $dir = storage_path('app/backups');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error('Cannot create backup directory '.$dir.'.');

            return null;
        }

        $path = $dir.DIRECTORY_SEPARATOR.'fresh-start-'.now()->format('Ymd-His').'.sql';
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --single-transaction --routines --events %s',
            escapeshellarg((string) ($connection['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($connection['port'] ?? '3306')),
            escapeshellarg((string) $connection['username']),
            escapeshellarg((string) $connection['database'])
        );

        $env = array_merge(getenv() ?: [], ['MYSQL_PWD' => (string) $connection['password']]);
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);

        if (! is_resource($process)) {
            $this->error('Could not start mysqldump — is it installed and on PATH?');

            return null;
        }

        $dump = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || $dump === false || $dump === '') {
            if (is_file($path)) {
                unlink($path);
            }
            $this->error('mysqldump failed (exit '.$exitCode.'). Nothing deleted.'.($errors !== '' ? ' mysqldump said: '.trim($errors) : ''));

            return null;
        }

        file_put_contents($path, $dump);

        if (! is_file($path) || filesize($path) <= 0) {
            if (is_file($path)) {
                unlink($path);
            }
            $this->error('Backup file is missing or empty — nothing deleted.');

            return null;
        }

        return $path;
    }

    /**
     * The same counts and Financial-Reports-style YTD figures before and
     * after the wipe, so the owner can confirm the fresh start.
     */
    private function printFigures(string $stage): void
    {
        $this->line('--- '.$stage.' ---');

        $grouped = Appointment::withTrashed()
            ->selectRaw('user_id, COUNT(*) AS c')
            ->groupBy('user_id')
            ->orderBy('user_id')
            ->get();

        if ($grouped->isEmpty()) {
            $this->line('Appointments: 0');
        } else {
            foreach ($grouped as $row) {
                $name = $row->user_id === null
                    ? '(walk-in)'
                    : (User::withTrashed()->find($row->user_id)?->name ?? '(deleted user)');
                $this->line("Appointments for user_id {$row->user_id} [{$name}]: {$row->c}");
            }
            $this->line('Appointments total: '.$grouped->sum('c'));
        }

        $this->line('Payments total: '.Payment::withTrashed()->count());
        $this->line('Commissions total: '.Commission::count());

        // Same definitions as ReportController::financial().
        $yearRevenue = round((float) Payment::where('status', PaymentStatus::Verified)
            ->whereYear('verified_at', now()->year)
            ->sum('amount'), 2);
        $yearCommissions = round((float) Commission::whereYear('earned_at', now()->year)
            ->sum('amount'), 2);

        $this->line('Verified payments sum (YTD): '.$yearRevenue);
        $this->line('Commissions sum (YTD): '.$yearCommissions);
    }
}
