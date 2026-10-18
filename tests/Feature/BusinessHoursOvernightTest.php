<?php

namespace Tests\Feature;

use App\Models\BusinessHours;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Business Hours overhaul: per-day-type defaults, overnight-aware validation,
 * the ONE shared slot rule (including post-midnight closes), and the
 * lock-after-save view. Weekly Roster and Service Hours are out of scope.
 */
class BusinessHoursOvernightTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    // ---------- A) Fresh defaults ----------

    public function test_fresh_seed_uses_per_day_type_defaults(): void
    {
        // Weekdays (Mon–Fri): 12:00 PM – 10:00 PM.
        foreach ([1, 2, 3, 4, 5] as $day) {
            $this->assertSame('12:00:00', \DB::table('business_hours')->where('day_of_week', $day)->value('open_at'), "weekday {$day} open");
            $this->assertSame('22:00:00', \DB::table('business_hours')->where('day_of_week', $day)->value('close_at'), "weekday {$day} close");
        }

        // Weekends (Sat–Sun): 10:00 AM – 10:00 PM.
        foreach ([0, 6] as $day) {
            $this->assertSame('10:00:00', \DB::table('business_hours')->where('day_of_week', $day)->value('open_at'), "weekend {$day} open");
            $this->assertSame('22:00:00', \DB::table('business_hours')->where('day_of_week', $day)->value('close_at'), "weekend {$day} close");
        }

        $this->assertSame(7, BusinessHours::count());
        $this->assertSame(7, BusinessHours::where('is_open', true)->count());
    }

    // ---------- B) Overnight-aware validation ----------

    private function hoursPayload(array $overrides = []): array
    {
        $hours = [];
        foreach (range(0, 6) as $day) {
            $hours[$day] = $overrides[$day] ?? ['is_open' => '1', 'open_at' => '12:00', 'close_at' => '13:00'];
        }

        return ['hours' => $hours];
    }

    public function test_overnight_pair_saves(): void
    {
        $this->actingAs($this->manager())
            ->post(route('admin.availability.business-hours'), $this->hoursPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame('13:00', Carbon::parse(\DB::table('business_hours')->where('day_of_week', 1)->value('close_at'))->format('H:i'));
    }

    public function test_close_equals_open_is_rejected(): void
    {
        $this->actingAs($this->manager())
            ->post(route('admin.availability.business-hours'), $this->hoursPayload([
                1 => ['is_open' => '1', 'open_at' => '12:00', 'close_at' => '12:00'],
            ]))
            ->assertSessionHasErrors('hours.1.close_at');
    }

    public function test_closed_day_saves_without_times(): void
    {
        $this->actingAs($this->manager())
            ->post(route('admin.availability.business-hours'), $this->hoursPayload([
                2 => ['is_open' => '0'],
            ]))
            ->assertSessionHasNoErrors();

        $row = BusinessHours::where('day_of_week', 2)->firstOrFail();
        $this->assertFalse((bool) $row->is_open);
    }

    public function test_ordinary_same_day_pair_saves(): void
    {
        $this->actingAs($this->manager())
            ->post(route('admin.availability.business-hours'), $this->hoursPayload([
                1 => ['is_open' => '1', 'open_at' => '09:00', 'close_at' => '18:00'],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('09:00', Carbon::parse(\DB::table('business_hours')->where('day_of_week', 1)->value('open_at'))->format('H:i'));
        $this->assertSame('18:00', Carbon::parse(\DB::table('business_hours')->where('day_of_week', 1)->value('close_at'))->format('H:i'));
    }

    // ---------- C) Shared slot rule ----------

    private function manager(): User
    {
        return User::where('email', 'manager@nexflio.test')->firstOrFail();
    }

    private function client(): User
    {
        return User::where('email', 'client@nexflio.test')->firstOrFail();
    }

    private function makeStaff(string $first, string $last): User
    {
        $user = User::create([
            'name' => "{$first} {$last}",
            'email' => strtolower("{$first}.{$last}@nexflio.test"),
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_STAFF,
        ]);
        StaffProfile::create([
            'user_id' => $user->id,
            'position' => 'Nail Technician',
            'employment_status' => 'active',
        ]);

        return $user;
    }

    private function makeService(int $duration): Service
    {
        return Service::create([
            'name' => "Overnight Nails {$duration}", 'category' => 'Nails', 'description' => 'Test',
            'price' => 199, 'duration_minutes' => $duration, 'status' => 'active',
            'service_location_type' => 'branch',
        ]);
    }

    private function setAllDays(string $open, string $close): void
    {
        foreach (range(0, 6) as $day) {
            BusinessHours::updateOrCreate(
                ['day_of_week' => $day],
                ['is_open' => true, 'open_at' => $open, 'close_at' => $close]
            );
        }
    }

    /** A date in the booking window whose day-of-week equals $dow, never today. */
    private function nextWeekday(int $dow): string
    {
        $date = now()->addDays(7)->startOfWeek(Carbon::MONDAY);
        while ((int) $date->dayOfWeek !== $dow) {
            $date->addDay();
        }

        return $date->toDateString();
    }

    private function slotHtml(string $date, Service $service, User $staff): string
    {
        return $this->actingAs($this->client())->get(route('account.booking.branch.schedule', [
            'service' => $service->id, 'date' => $date, 'personnel' => (string) $staff->id,
        ]))->assertOk()->getContent();
    }

    public function test_default_close_last_start_is_9pm_for_60_minute_service(): void
    {
        // Seeded defaults: close 10:00 PM — 60-min service last starts 9:00 PM.
        $staff = $this->makeStaff('Nora', 'Night');
        $service = $this->makeService(60);

        $html = $this->slotHtml($this->nextWeekday(1), $service, $staff); // Monday

        $this->assertStringContainsString('time=21%3A00', $html); // 9:00 PM — last start
        $this->assertStringNotContainsString('time=21%3A30', $html); // would finish after 10 PM
        $this->assertStringNotContainsString('time=22%3A00', $html); // not at/after closing
        $this->assertStringNotContainsString('time=10%3A00', $html); // opens at noon, not 10 AM
    }

    public function test_two_hour_service_last_start_is_8pm(): void
    {
        $staff = $this->makeStaff('Tia', 'Twohour');
        $service = $this->makeService(120);

        $html = $this->slotHtml($this->nextWeekday(1), $service, $staff);

        $this->assertStringContainsString('time=20%3A00', $html); // 8:00 PM — last start
        $this->assertStringNotContainsString('time=20%3A30', $html); // would finish after 10 PM
    }

    public function test_weekend_slots_open_in_the_morning(): void
    {
        $staff = $this->makeStaff('Wendy', 'Weekend');
        $service = $this->makeService(60);

        $html = $this->slotHtml($this->nextWeekday(6), $service, $staff); // Saturday

        $this->assertStringContainsString('time=10%3A00', $html);
        $this->assertStringContainsString('time=21%3A00', $html);
        $this->assertStringNotContainsString('time=22%3A00', $html);
    }

    public function test_overnight_hours_still_allow_late_starts_when_configured(): void
    {
        // Managers may still set an overnight close; slot rule stays overnight-aware.
        $this->setAllDays('12:00', '01:00');
        $staff = $this->makeStaff('Ivy', 'Overnight');
        $service = $this->makeService(60);

        $html = $this->slotHtml($this->nextWeekday(1), $service, $staff);

        $this->assertStringContainsString('time=23%3A30', $html);
        $this->assertStringNotContainsString('time=00%3A00', $html);
    }

    public function test_same_day_close_last_start(): void
    {
        $this->setAllDays('09:00', '18:00');
        $staff = $this->makeStaff('Sam', 'Samday');
        $service = $this->makeService(60);

        $html = $this->slotHtml($this->nextWeekday(3), $service, $staff); // Wednesday

        $this->assertStringContainsString('time=17%3A00', $html); // 5:00 PM — last start
        $this->assertStringNotContainsString('time=17%3A30', $html);
    }

    // ---------- D) Lock-after-save ----------

    public function test_locked_view_shows_text_and_enabled_edit(): void
    {
        $html = $this->actingAs($this->manager())
            ->get(route('admin.availability'))->assertOk()->getContent();

        $this->assertStringContainsString('12:00 PM', $html);
        $this->assertStringContainsString('10:00 PM', $html);
        $this->assertStringContainsString('edit=1', $html); // enabled Edit link
        $this->assertStringNotContainsString('name="hours[', $html); // no inputs
        $this->assertStringNotContainsString('Save Business Hours', $html);
    }

    public function test_edit_mode_shows_form_with_disabled_edit_and_cancel(): void
    {
        $html = $this->actingAs($this->manager())
            ->get(route('admin.availability', ['edit' => 1]))->assertOk()->getContent();

        $this->assertStringContainsString('name="hours[0][open_at]"', $html);
        $this->assertStringContainsString('Save Business Hours', $html);
        $this->assertStringContainsString('Cancel', $html);
        $this->assertStringContainsString('disabled', $html);
    }

    public function test_save_redirects_to_locked_view(): void
    {
        $this->actingAs($this->manager())
            ->post(route('admin.availability.business-hours'), $this->hoursPayload())
            ->assertRedirect(route('admin.availability').'#tab-hours')
            ->assertSessionHas('success');
    }

    // ---------- C4) Public lines ----------

    public function test_public_lines_group_weekday_and_weekend(): void
    {
        $lines = BusinessHours::publicLines();

        $this->assertContains('Monday – Friday: 12:00 PM – 10:00 PM', $lines);
        $this->assertContains('Saturday – Sunday: 10:00 AM – 10:00 PM', $lines);
    }
}
