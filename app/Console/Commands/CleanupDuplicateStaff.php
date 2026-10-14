<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Attendance;
use App\Models\Commission;
use App\Models\StaffProfile;
use App\Models\StaffSchedule;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * One-off cleanup for duplicates created by the old StaffEmployeesSeeder
 * bug (its username exists-check counted the staff member's own account,
 * suffixed it, and updateOrCreate-by-email then spawned a new user).
 *
 * A candidate is a staff user whose username is a base name plus trailing
 * digits (e.g. castillo.jasmine10) while another account still holds the
 * base username (castillo.jasmine). Candidates WITH linked records —
 * appointments as personnel, attendances, commissions, or staff schedules
 * — are SKIPPED and reported, never deleted. A deletable candidate loses
 * its StaffProfile first, then the user is force-deleted.
 *
 * --dry-run reports without deleting anything.
 */
class CleanupDuplicateStaff extends Command
{
    protected $signature = 'staff:cleanup-duplicates {--dry-run : Report candidates without deleting anything}';

    protected $description = 'Delete duplicate staff accounts spawned by the old seeder bug (skips accounts with linked records)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = User::withTrashed()
            ->where('role_id', User::ROLE_STAFF)
            ->get()
            ->filter(function (User $user) {
                if (! preg_match('/^([a-z]+\.[a-z]+)[0-9]+$/', (string) $user->username, $matches)) {
                    return false;
                }

                return User::withTrashed()
                    ->where('username', $matches[1])
                    ->where('id', '!=', $user->id)
                    ->exists();
            })
            ->values();

        if ($candidates->isEmpty()) {
            $this->info('No duplicate staff accounts found.');

            return self::SUCCESS;
        }

        foreach ($candidates as $candidate) {
            $links = $this->linkedRecords($candidate->id);

            if ($links !== []) {
                $this->line('SKIPPED '.$candidate->username.' — has linked records ('.implode(', ', $links).')');
                continue;
            }

            if ($dryRun) {
                $this->line('WOULD DELETE '.$candidate->username.' (id '.$candidate->id.')');
                continue;
            }

            StaffProfile::where('user_id', $candidate->id)->delete();
            $candidate->forceDelete();

            $this->line('DELETED '.$candidate->username.' (id '.$candidate->id.')');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string> kinds of linked records blocking deletion.
     */
    private function linkedRecords(int $userId): array
    {
        $links = [];

        if (Appointment::withTrashed()->where('personnel_id', $userId)->exists()) {
            $links[] = 'appointments';
        }
        if (Attendance::where('user_id', $userId)->exists()) {
            $links[] = 'attendances';
        }
        if (Commission::where('user_id', $userId)->exists()) {
            $links[] = 'commissions';
        }
        if (StaffSchedule::where('user_id', $userId)->exists()) {
            $links[] = 'staff schedules';
        }

        return $links;
    }
}
