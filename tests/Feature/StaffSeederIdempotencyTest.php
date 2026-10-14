<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\StaffEmployeesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * StaffEmployeesSeeder must be safely re-runnable (same 10 accounts updated
 * in place, lastname passwords) and the staff:cleanup-duplicates command
 * must remove only unlinked digit-suffixed duplicates.
 */
class StaffSeederIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** @var array<string, string> base email => base username. */
    private array $expected = [
        'reyes.angelica@nexflio.test' => 'reyes.angelica',
        'santos.bianca@nexflio.test' => 'santos.bianca',
        'lopez.catherine@nexflio.test' => 'lopez.catherine',
        'garcia.danica@nexflio.test' => 'garcia.danica',
        'rivera.ericka@nexflio.test' => 'rivera.ericka',
        'cruz.fiona@nexflio.test' => 'cruz.fiona',
        'mendoza.gwen@nexflio.test' => 'mendoza.gwen',
        'torres.hazel@nexflio.test' => 'torres.hazel',
        'flores.irene@nexflio.test' => 'flores.irene',
        'castillo.jasmine@nexflio.test' => 'castillo.jasmine',
    ];

    public function test_seeder_twice_keeps_exactly_ten_accounts_with_lastname_passwords(): void
    {
        $this->seed(StaffEmployeesSeeder::class);
        $firstIds = $this->seededIds();

        $this->seed(StaffEmployeesSeeder::class);

        // Same 10 accounts — same ids, same usernames, same emails.
        $this->assertSame($firstIds, $this->seededIds());

        foreach ($this->expected as $email => $username) {
            $matches = User::where('email', $email)->get();
            $this->assertCount(1, $matches, "Expected exactly one user for {$email}");
            $this->assertSame($username, $matches->first()->username);
        }

        // No digit-suffixed duplicates among the seeded staff.
        $suffixed = User::where('role_id', User::ROLE_STAFF)
            ->pluck('username')
            ->filter(fn ($username) => preg_match('/^[a-z]+\.[a-z]+[0-9]+$/', (string) $username))
            ->all();
        $this->assertSame([], array_values($suffixed));

        $jasmine = User::where('email', 'castillo.jasmine@nexflio.test')->firstOrFail();
        $this->assertTrue(Hash::check('castillo00000', $jasmine->password));
    }

    public function test_cleanup_deletes_only_unlinked_duplicates(): void
    {
        $this->seed(StaffEmployeesSeeder::class);

        // A leftover duplicate with nothing linked to it.
        $dup = User::create([
            'name' => 'Jasmine Castillo', 'first_name' => 'Jasmine', 'last_name' => 'Castillo',
            'username' => 'castillo.jasmine10', 'email' => 'castillo.jasmine10@nexflio.test',
            'password' => bcrypt('password'), 'role_id' => User::ROLE_STAFF,
        ]);
        StaffProfile::create([
            'user_id' => $dup->id, 'base_pay' => 12400, 'commission_rate' => 10,
            'position' => 'Nail Technician', 'employment_status' => 'active',
            'hired_at' => now()->toDateString(),
        ]);

        // A duplicate that DOES have linked records (an attendance row).
        $linked = User::create([
            'name' => 'Angelica Reyes', 'first_name' => 'Angelica', 'last_name' => 'Reyes',
            'username' => 'reyes.angelica1', 'email' => 'reyes.angelica1@nexflio.test',
            'password' => bcrypt('password'), 'role_id' => User::ROLE_STAFF,
        ]);
        Attendance::create([
            'user_id' => $linked->id, 'work_date' => now()->toDateString(), 'time_in' => now(),
        ]);

        // Dry run deletes nothing.
        $this->artisan('staff:cleanup-duplicates', ['--dry-run' => true])
            ->expectsOutputToContain('WOULD DELETE castillo.jasmine10 (id '.$dup->id.')')
            ->expectsOutputToContain('SKIPPED reyes.angelica1')
            ->assertSuccessful();
        $this->assertNotNull(User::withTrashed()->find($dup->id));

        // Real run deletes the unlinked duplicate (profile first), skips the linked one.
        $this->artisan('staff:cleanup-duplicates')
            ->expectsOutputToContain('DELETED castillo.jasmine10 (id '.$dup->id.')')
            ->expectsOutputToContain('SKIPPED reyes.angelica1')
            ->assertSuccessful();
        $this->assertNull(User::withTrashed()->find($dup->id));
        $this->assertSame(0, StaffProfile::withTrashed()->where('user_id', $dup->id)->count());
        $this->assertNotNull(User::find($linked->id));

        // Idempotent: a second run changes nothing — the linked duplicate is
        // reported (and skipped) again, the deleted one never reappears.
        $this->artisan('staff:cleanup-duplicates')
            ->expectsOutputToContain('SKIPPED reyes.angelica1')
            ->doesntExpectOutputToContain('DELETED')
            ->assertSuccessful();
        $this->assertNull(User::withTrashed()->find($dup->id));
    }

    /** @return array<string, int> base email => user id. */
    private function seededIds(): array
    {
        $ids = [];
        foreach (array_keys($this->expected) as $email) {
            $ids[$email] = User::where('email', $email)->firstOrFail()->id;
        }

        return $ids;
    }
}
