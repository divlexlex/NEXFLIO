<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\StaffSchedule;
use App\Models\StaffShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $staff;

    private User $manager;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ===== Break toggle =====

    public function test_staff_can_toggle_break_while_timed_in(): void
    {
        $this->actingAs($this->staff)->post('/staff/attendance/time-in')->assertSessionHasNoErrors();

        $this->actingAs($this->staff)->post(route('staff.attendance.break'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $profile = $this->staff->staffProfile->fresh();
        $this->assertTrue($profile->is_on_break);
        $this->assertNotNull($profile->break_started_at);

        $this->actingAs($this->staff)->post(route('staff.attendance.break'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $profile = $this->staff->staffProfile->fresh();
        $this->assertFalse($profile->is_on_break);
        $this->assertNull($profile->break_started_at);
    }

    public function test_break_toggle_is_refused_before_time_in(): void
    {
        $this->assertFalse($this->staff->staffProfile->is_on_break);

        $this->actingAs($this->staff)->post(route('staff.attendance.break'))
            ->assertRedirect()
            ->assertSessionHasErrors('attendance');

        $this->assertFalse($this->staff->staffProfile->fresh()->is_on_break);
    }

    public function test_break_toggle_is_refused_after_time_out(): void
    {
        Attendance::create([
            'user_id' => $this->staff->id,
            'work_date' => now()->toDateString(),
            'time_in' => now()->subHours(2),
            'time_out' => now()->subHour(),
        ]);

        $this->actingAs($this->staff)->post(route('staff.attendance.break'))
            ->assertSessionHasErrors('attendance');

        $this->assertFalse($this->staff->staffProfile->fresh()->is_on_break);
    }

    public function test_timing_out_clears_break_state(): void
    {
        $this->actingAs($this->staff)->post('/staff/attendance/time-in');
        $this->actingAs($this->staff)->post(route('staff.attendance.break'));

        $this->assertTrue($this->staff->staffProfile->fresh()->is_on_break);

        $this->actingAs($this->staff)->post('/staff/attendance/time-out')->assertSessionHasNoErrors();

        $profile = $this->staff->staffProfile->fresh();
        $this->assertFalse($profile->is_on_break);
        $this->assertNull($profile->break_started_at);
    }

    public function test_break_toggle_without_profile_does_not_error(): void
    {
        $user = User::create([
            'name' => 'No Profile Staff',
            'email' => 'noprofile@nexflio.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_STAFF,
            'email_verified_at' => now(),
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'time_in' => now()->subHour(),
        ]);

        $this->actingAs($user)->post(route('staff.attendance.break'))
            ->assertRedirect()
            ->assertSessionHasErrors('attendance');
    }

    public function test_on_break_flag_alone_removes_staff_from_booking_pool(): void
    {
        $pool = $this->actingAs($this->client, 'sanctum')->getJson('/api/personnel');
        $this->assertContains($this->staff->id, array_column($pool->json(), 'id'));

        $this->staff->staffProfile->update(['is_on_break' => true, 'break_started_at' => now()]);

        $pool = $this->actingAs($this->client, 'sanctum')->getJson('/api/personnel');
        $this->assertNotContains($this->staff->id, array_column($pool->json(), 'id'));
    }

    // ===== Total hours =====

    public function test_worked_time_is_shown_on_staff_and_admin_views(): void
    {
        $date = '2026-10-14';
        $this->travelTo(Carbon::parse($date.' 18:00:00'));

        Attendance::create([
            'user_id' => $this->staff->id,
            'work_date' => $date,
            'time_in' => Carbon::parse($date.' 09:00:00'),
            'time_out' => Carbon::parse($date.' 17:30:00'),
        ]);

        $attendance = Attendance::where('user_id', $this->staff->id)->first();
        $this->assertSame(510, $attendance->workedMinutes());
        $this->assertSame('8h 30m', $attendance->workedForHumans());

        $this->actingAs($this->staff)->get('/staff/dashboard')
            ->assertOk()
            ->assertSee('Total today: 8h 30m');

        $this->actingAs($this->manager)->get('/admin/employees?date='.$date)
            ->assertOk()
            ->assertSee('8h 30m');
    }

    public function test_open_shift_shows_total_so_far(): void
    {
        $date = '2026-10-14';
        $this->travelTo(Carbon::parse($date.' 14:00:00'));

        $attendance = Attendance::create([
            'user_id' => $this->staff->id,
            'work_date' => $date,
            'time_in' => Carbon::parse($date.' 12:30:00'),
        ]);

        $this->assertSame(90, $attendance->workedMinutes());
        $this->assertGreaterThanOrEqual(0, $attendance->workedMinutes());

        $this->actingAs($this->staff)->get('/staff/dashboard')
            ->assertOk()
            ->assertSee('Total so far: 1h 30m');
    }

    public function test_worked_human_format_edge_cases(): void
    {
        $eightHours = new Attendance([
            'time_in' => '2026-10-14 09:00:00',
            'time_out' => '2026-10-14 17:00:00',
        ]);
        $this->assertSame('8h 00m', $eightHours->workedForHumans());

        $fortyFive = new Attendance([
            'time_in' => '2026-10-14 09:00:00',
            'time_out' => '2026-10-14 09:45:00',
        ]);
        $this->assertSame('45m', $fortyFive->workedForHumans());

        $this->assertNull((new Attendance)->workedForHumans());
    }

    // ===== Late =====

    public function test_late_is_measured_against_roster_assignment(): void
    {
        $date = '2026-10-14';
        $this->travelTo(Carbon::parse($date.' 10:12:00'));

        StaffShiftAssignment::create([
            'user_id' => $this->staff->id,
            'week_start' => StaffShiftAssignment::weekStartOf($date),
            'start_time' => '10:00:00',
            'end_time' => '18:00:00',
        ]);

        $attendance = Attendance::create([
            'user_id' => $this->staff->id,
            'work_date' => $date,
            'time_in' => Carbon::parse($date.' 10:12:00'),
        ]);

        $this->assertSame(2, $attendance->lateMinutes());
        $this->assertTrue($attendance->isLate());

        $this->actingAs($this->staff)->get('/staff/dashboard')
            ->assertOk()
            ->assertSee('Late 2m');
    }

    public function test_on_time_against_roster_is_not_late(): void
    {
        $date = '2026-10-14';
        $this->travelTo(Carbon::parse($date.' 09:55:00'));

        StaffShiftAssignment::create([
            'user_id' => $this->staff->id,
            'week_start' => StaffShiftAssignment::weekStartOf($date),
            'start_time' => '10:00:00',
            'end_time' => '18:00:00',
        ]);

        $attendance = Attendance::create([
            'user_id' => $this->staff->id,
            'work_date' => $date,
            'time_in' => Carbon::parse($date.' 09:55:00'),
        ]);

        $this->assertSame(0, $attendance->lateMinutes());
        $this->assertFalse($attendance->isLate());

        $this->actingAs($this->staff)->get('/staff/dashboard')
            ->assertOk()
            ->assertDontSee('Late');
    }

    public function test_late_falls_back_to_standing_schedule(): void
    {
        $date = '2026-10-14';
        $this->travelTo(Carbon::parse($date.' 09:20:00'));

        StaffSchedule::create([
            'user_id' => $this->staff->id,
            'day_of_week' => Carbon::parse($date)->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_available' => true,
        ]);

        $attendance = Attendance::create([
            'user_id' => $this->staff->id,
            'work_date' => $date,
            'time_in' => Carbon::parse($date.' 09:20:00'),
        ]);

        $this->assertSame(10, $attendance->lateMinutes());
        $this->assertTrue($attendance->isLate());

        $this->actingAs($this->manager)->get('/admin/employees?date='.$date)
            ->assertOk()
            ->assertSee('Late 10m');
    }

    public function test_no_schedule_means_no_late_data(): void
    {
        $date = '2026-10-14';
        $this->travelTo(Carbon::parse($date.' 10:12:00'));

        $attendance = Attendance::create([
            'user_id' => $this->staff->id,
            'work_date' => $date,
            'time_in' => Carbon::parse($date.' 10:12:00'),
        ]);

        $this->assertNull($attendance->scheduledStart());
        $this->assertNull($attendance->lateMinutes());
        $this->assertFalse($attendance->isLate());

        $this->actingAs($this->staff)->get('/staff/dashboard')
            ->assertOk()
            ->assertDontSee('Late');
    }
}
