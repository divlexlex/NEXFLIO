<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\StaffShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffAttendancePageTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $staff;

    private User $otherStaff;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();

        $this->otherStaff = User::create([
            'name' => 'Other Staff',
            'email' => 'other.attendance@nexflio.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_STAFF,
            'email_verified_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function row(User $user, string $date, string $in, ?string $out = null): Attendance
    {
        return Attendance::create([
            'user_id' => $user->id,
            'work_date' => $date,
            'time_in' => Carbon::parse($date.' '.$in),
            'time_out' => $out ? Carbon::parse($date.' '.$out) : null,
        ]);
    }

    public function test_staff_only_sees_their_own_attendance_rows(): void
    {
        $date = '2026-10-14';
        $this->travelTo(Carbon::parse($date.' 18:00:00'));

        $this->row($this->staff, $date, '09:00:00', '17:00:00');
        $this->row($this->otherStaff, $date, '08:03:00', '16:00:00');

        $this->actingAs($this->staff)->get('/staff/attendance')
            ->assertOk()
            ->assertSee('9:00 AM')
            ->assertDontSee('8:03 AM');
    }

    public function test_monthly_summary_counts_days_total_hours_and_lates(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 18:00:00'));

        // One roster assignment covers the whole week (Sun–Sat): 10:00 start
        // with the model's 10-minute grace.
        StaffShiftAssignment::create([
            'user_id' => $this->staff->id,
            'week_start' => StaffShiftAssignment::weekStartOf('2026-10-12'),
            'start_time' => '10:00:00',
            'end_time' => '18:00:00',
        ]);

        $this->row($this->staff, '2026-10-12', '09:00:00', '17:00:00'); // 480m, on time
        $this->row($this->staff, '2026-10-13', '10:12:00', '17:12:00'); // 420m, late 2m

        $this->actingAs($this->staff)->get('/staff/attendance')
            ->assertOk()
            ->assertViewHas('daysPresent', 2)
            ->assertViewHas('totalHours', '15h 00m')
            ->assertViewHas('lateCount', 1)
            ->assertSee('Late 2m');
    }

    public function test_past_month_renders_that_month_and_invalid_falls_back(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 18:00:00'));

        $this->row($this->staff, '2026-09-10', '09:00:00', '12:30:00');

        $this->actingAs($this->staff)->get('/staff/attendance?month=2026-09')
            ->assertOk()
            ->assertViewHas('daysPresent', 1)
            ->assertViewHas('totalHours', '3h 30m')
            ->assertSee('September 2026')
            ->assertSee('9:00 AM');

        $this->actingAs($this->staff)->get('/staff/attendance?month=garbage')
            ->assertOk()
            ->assertViewHas('daysPresent', 0)
            ->assertViewHas('totalHours', '0m')
            ->assertSee('October 2026');

        $this->actingAs($this->staff)->get('/staff/attendance?month=2026-13')
            ->assertOk()
            ->assertViewHas('daysPresent', 0)
            ->assertSee('October 2026');
    }

    public function test_future_days_are_not_listed_and_missing_days_say_no_record(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 18:00:00'));

        $this->row($this->staff, '2026-10-13', '09:00:00', '17:00:00');

        $this->actingAs($this->staff)->get('/staff/attendance')
            ->assertOk()
            ->assertSee('Oct 14')      // today IS listed
            ->assertSee('No record')   // days without a row
            ->assertDontSee('Oct 15')
            ->assertDontSee('Oct 16');
    }

    public function test_today_card_present_and_time_in_works_from_page(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 08:00:00'));

        $this->actingAs($this->staff)->get('/staff/attendance')
            ->assertOk()
            ->assertSee("Today's Attendance", false)
            ->assertSee('Time In');

        $this->actingAs($this->staff)->post(route('staff.attendance.time-in'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->staff->id,
        ]);

        $this->actingAs($this->staff)->get('/staff/attendance')
            ->assertOk()
            ->assertSee('Timed in at', false)
            ->assertSee('8:00 AM');
    }

    public function test_guests_are_redirected_and_clients_are_forbidden(): void
    {
        $this->get('/staff/attendance')->assertRedirect(route('login'));

        $this->actingAs($this->client)->get('/staff/attendance')->assertForbidden();
    }
}
