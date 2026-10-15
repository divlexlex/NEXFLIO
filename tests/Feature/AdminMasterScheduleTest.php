<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\LeaveRequest;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin "Master Schedule" — weekly monitoring grid over active staff: roster
 * shifts, per-day appointments, leave markers, and the day detail panel.
 */
class AdminMasterScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $owner;

    private User $manager;

    private User $staff;

    private User $client;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::where('email', 'admin@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->service = Service::where('status', 'active')->firstOrFail();

        $this->staff = User::create([
            'name' => 'Master Sched Staff',
            'email' => 'master.sched@nexflio.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_STAFF,
            'email_verified_at' => now(),
        ]);
        StaffProfile::create([
            'user_id' => $this->staff->id,
            'position' => 'Nail Technician',
            'employment_status' => 'active',
        ]);
    }

    private function makeAppointment(User $personnel, string $date, string $time = '14:00:00', AppointmentStatus $status = AppointmentStatus::Booked): Appointment
    {
        return Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $personnel->id,
            'appointment_date' => $date,
            'start_time' => $time,
            'status' => $status,
        ]);
    }

    public function test_owner_and_manager_can_open_forbidden_for_staff_and_client(): void
    {
        $this->actingAs($this->owner)->get(route('admin.master-schedule'))->assertOk();
        $this->actingAs($this->manager)->get(route('admin.master-schedule'))->assertOk();

        $this->actingAs($this->staff)->get(route('admin.master-schedule'))->assertForbidden();
        $this->actingAs($this->client)->get(route('admin.master-schedule'))->assertForbidden();
    }

    public function test_week_appointment_shown_other_week_hidden(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->addDays(14)->toDateString());
        $inWeek = Carbon::parse($weekStart)->addDays(2)->toDateString();
        $otherWeek = Carbon::parse($weekStart)->addDays(10)->toDateString();

        $this->makeAppointment($this->staff, $inWeek);

        $otherService = Service::create([
            'name' => 'OtherWeek OnlySvc Xyz',
            'category' => 'Facial',
            'description' => 'Test',
            'price' => 100,
            'duration_minutes' => 30,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);

        $other = $this->makeAppointment($this->staff, $otherWeek);
        $other->update(['service_id' => $otherService->id]);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.master-schedule', ['week' => $weekStart]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($this->service->name, $html);
        $this->assertStringContainsString($this->client->name, $html);
        $this->assertStringNotContainsString($otherService->name, $html);
        $this->assertStringContainsString('Week of ', $html);
    }

    public function test_shift_label_shown_or_not_assigned(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->addDays(14)->toDateString());

        StaffShiftAssignment::create([
            'user_id' => $this->staff->id,
            'week_start' => $weekStart,
            'shift' => 'am',
            'start_time' => '10:00',
            'end_time' => '16:00',
        ]);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.master-schedule', ['week' => $weekStart]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('AM · 10:00 AM – 4:00 PM', $html);
        // Seeded staff without an assignment for that week.
        $this->assertStringContainsString('Not assigned', $html);
    }

    public function test_cancelled_and_no_show_appointments_do_not_appear(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->addDays(14)->toDateString());
        $date = Carbon::parse($weekStart)->addDays(2)->toDateString();

        $cancelled = $this->makeAppointment($this->staff, $date, '10:00:00', AppointmentStatus::Cancelled);
        $noShow = $this->makeAppointment($this->staff, $date, '11:00:00', AppointmentStatus::NoShow);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.master-schedule', ['week' => $weekStart]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($cancelled->referenceCode(), $html);
        $this->assertStringNotContainsString($noShow->referenceCode(), $html);
    }

    public function test_detail_panel_shows_reference_code_for_selected_day(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->addDays(14)->toDateString());
        $date = Carbon::parse($weekStart)->addDays(3)->toDateString();

        $appointment = $this->makeAppointment($this->staff, $date);

        // Without a selection the hint shows instead.
        $plain = $this->actingAs($this->manager)
            ->get(route('admin.master-schedule', ['week' => $weekStart]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString("Select a cell to see that day's appointments.", $plain);
        $this->assertStringNotContainsString($appointment->referenceCode(), $plain);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.master-schedule', [
                'week' => $weekStart,
                'staff' => $this->staff->id,
                'date' => $date,
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($appointment->referenceCode(), $html);
        $this->assertStringContainsString($this->staff->fullName(), $html);
    }

    public function test_approved_leave_shows_on_leave_chip(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->addDays(14)->toDateString());
        $date = Carbon::parse($weekStart)->addDays(4)->toDateString();

        LeaveRequest::create([
            'user_id' => $this->staff->id,
            'start_date' => $date,
            'end_date' => $date,
            'type' => 'vacation',
            'reason' => 'Day off.',
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.master-schedule', ['week' => $weekStart]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('On leave', $html);
    }
}
