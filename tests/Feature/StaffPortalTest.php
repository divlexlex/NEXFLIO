<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Attendance;
use App\Models\Commission;
use App\Models\LeaveRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPortalTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $staff;

    private User $client;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->service = Service::where('status', 'active')->first();
    }

    public function test_staff_can_reach_their_dashboard(): void
    {
        $this->actingAs($this->staff)->get('/staff/dashboard')
            ->assertOk()
            ->assertSee('Time In')
            ->assertSee('Commissions This Month');
    }

    public function test_non_staff_cannot_reach_the_staff_dashboard(): void
    {
        $this->actingAs($this->client)->get('/staff/dashboard')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/staff/dashboard')->assertRedirect(route('login'));
    }

    public function test_staff_can_time_in_and_out_once_per_day(): void
    {
        $this->actingAs($this->staff)->post('/staff/attendance/time-in')
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            Attendance::where('user_id', $this->staff->id)->whereDate('work_date', now()->toDateString())->exists()
        );

        // Second time-in the same day is rejected.
        $this->actingAs($this->staff)->post('/staff/attendance/time-in')
            ->assertSessionHasErrors('attendance');

        $this->actingAs($this->staff)->post('/staff/attendance/time-out')
            ->assertSessionHasNoErrors();

        $attendance = Attendance::where('user_id', $this->staff->id)->first();
        $this->assertNotNull($attendance->time_out);

        // Nothing open left to time out from.
        $this->actingAs($this->staff)->post('/staff/attendance/time-out')
            ->assertSessionHasErrors('attendance');
    }

    public function test_dashboard_shows_only_this_staffs_assigned_appointments(): void
    {
        $otherStaff = User::create([
            'name' => 'Other Staff',
            'email' => 'other.staff@example.com',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_STAFF,
            'email_verified_at' => now(),
        ]);

        Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now(),
            'start_time' => '14:00:00',
            'status' => AppointmentStatus::Booked,
        ]);

        Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $otherStaff->id,
            'appointment_date' => now(),
            'start_time' => '15:00:00',
            'status' => AppointmentStatus::Booked,
        ]);

        $response = $this->actingAs($this->staff)->get('/staff/dashboard');
        $response->assertOk();
        $response->assertSee('2:00 PM'); // mine
        $response->assertDontSee('3:00 PM'); // the other staff's booking must not leak in
    }

    public function test_staff_can_submit_a_leave_request(): void
    {
        $response = $this->actingAs($this->staff)->post('/staff/leaves', [
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDay()->toDateString(),
            'type' => 'vacation',
            'reason' => 'Family trip.',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $this->staff->id,
            'type' => 'vacation',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
    }

    public function test_leave_request_rejects_overlapping_dates(): void
    {
        LeaveRequest::create([
            'user_id' => $this->staff->id,
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeek()->addDays(2),
            'type' => 'vacation',
            'reason' => 'First request.',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->staff)->post('/staff/leaves', [
            'start_date' => now()->addWeek()->addDay()->toDateString(),
            'end_date' => now()->addWeek()->addDays(3)->toDateString(),
            'type' => 'sick',
            'reason' => 'Overlapping request.',
        ]);

        $response->assertSessionHasErrors('start_date');
    }

    public function test_commissions_this_month_total_is_correct(): void
    {
        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now(),
            'start_time' => '10:00:00',
            'status' => AppointmentStatus::Completed,
        ]);

        Commission::create([
            'user_id' => $this->staff->id,
            'appointment_id' => $appointment->id,
            'service_price' => 1000,
            'rate' => 10,
            'amount' => 100,
            'earned_at' => now(),
        ]);

        $this->actingAs($this->staff)->get('/staff/dashboard')
            ->assertOk()
            ->assertSee('100.00');
    }

    public function test_login_redirects_staff_to_their_dashboard(): void
    {
        $this->post('/login', [
            'email' => $this->staff->email,
            'password' => 'delacruz00000',
        ])->assertRedirect(route('staff.dashboard'));
    }
}
