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
 * Staff "My Schedule" — personal read-only calendar: the page is staff-only
 * and the event feed carries only the logged-in staff member's own
 * appointments, roster shifts, and approved leave.
 */
class StaffScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $staff;

    private User $otherStaff;

    private User $client;

    private User $manager;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->service = Service::where('status', 'active')->firstOrFail();

        $this->otherStaff = User::create([
            'name' => 'Other Staff',
            'email' => 'other.staff@nexflio.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_STAFF,
            'email_verified_at' => now(),
        ]);
        StaffProfile::create([
            'user_id' => $this->otherStaff->id,
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

    private function feed(User $user, string $start, string $end): array
    {
        return $this->actingAs($user)
            ->get(route('staff.schedule.events', ['start' => $start, 'end' => $end]))
            ->assertOk()
            ->json();
    }

    public function test_schedule_page_is_staff_only(): void
    {
        $this->actingAs($this->staff)->get(route('staff.schedule'))
            ->assertOk()
            ->assertSee('My Schedule');

        $this->actingAs($this->client)->get(route('staff.schedule'))->assertForbidden();
        $this->actingAs($this->manager)->get(route('staff.schedule'))->assertForbidden();
    }

    public function test_feed_returns_only_the_logged_in_staffs_appointment(): void
    {
        $date = now()->addDays(10)->toDateString();

        $mine = $this->makeAppointment($this->staff, $date);
        $theirs = $this->makeAppointment($this->otherStaff, $date, '15:00:00');

        $events = collect($this->feed(
            $this->staff,
            now()->addDays(9)->toDateString(),
            now()->addDays(11)->toDateString()
        ));

        $byId = $events->keyBy('id');

        $this->assertTrue($byId->has($mine->id));
        $this->assertFalse($byId->has($theirs->id));

        $event = $byId->get($mine->id);
        $this->assertStringContainsString($mine->referenceCode(), $event['title']);
        $this->assertStringContainsString($this->client->name, $event['title']);
        $this->assertStringContainsString($this->service->name, $event['title']);
        $this->assertSame(['nx-evt', 'nx-evt-booked'], $event['classNames']);
        $this->assertSame('#dcf0e2', $event['backgroundColor']);
        $this->assertSame($mine->referenceCode(), $event['extendedProps']['reference_code']);
        $this->assertSame($this->client->name, $event['extendedProps']['client']);
        $this->assertSame($this->service->name, $event['extendedProps']['service']);
        $this->assertSame('booked', $event['extendedProps']['status']);
        $this->assertArrayHasKey('dateLabel', $event['extendedProps']);
        $this->assertArrayHasKey('timeLabel', $event['extendedProps']);
    }

    public function test_cancelled_and_no_show_appointments_are_absent(): void
    {
        $date = now()->addDays(10)->toDateString();

        $cancelled = $this->makeAppointment($this->staff, $date, '10:00:00', AppointmentStatus::Cancelled);
        $noShow = $this->makeAppointment($this->staff, $date, '11:00:00', AppointmentStatus::NoShow);

        $events = collect($this->feed(
            $this->staff,
            now()->addDays(9)->toDateString(),
            now()->addDays(11)->toDateString()
        ));

        $ids = $events->pluck('id')->all();
        $this->assertNotContains($cancelled->id, $ids);
        $this->assertNotContains($noShow->id, $ids);
    }

    public function test_shift_week_emits_seven_background_events_for_that_user_only(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->toDateString());
        $weekEnd = Carbon::parse($weekStart)->addDays(7)->toDateString();

        StaffShiftAssignment::create([
            'user_id' => $this->staff->id,
            'week_start' => $weekStart,
            'shift' => 'am',
            'start_time' => '10:00',
            'end_time' => '16:00',
        ]);

        $mine = collect($this->feed($this->staff, $weekStart, $weekEnd))
            ->where('extendedProps.kind', 'shift')
            ->values();

        $this->assertCount(7, $mine);
        $this->assertTrue($mine->every(fn ($event) => ($event['display'] ?? null) === 'background'));
        $this->assertTrue($mine->every(fn ($event) => $event['title'] === '10:00 AM – 4:00 PM'));

        $theirs = collect($this->feed($this->otherStaff, $weekStart, $weekEnd))
            ->where('extendedProps.kind', 'shift');

        $this->assertCount(0, $theirs);
    }

    public function test_approved_leave_emits_all_day_event_pending_does_not(): void
    {
        $start = now()->addDays(10)->toDateString();
        $end = now()->addDays(12)->toDateString();

        $approved = LeaveRequest::create([
            'user_id' => $this->staff->id,
            'start_date' => $start,
            'end_date' => $end,
            'type' => 'vacation',
            'reason' => 'Family trip.',
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        LeaveRequest::create([
            'user_id' => $this->staff->id,
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(21)->toDateString(),
            'type' => 'sick',
            'reason' => 'Not yet reviewed.',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $leaves = collect($this->feed(
            $this->staff,
            now()->addDays(9)->toDateString(),
            now()->addDays(13)->toDateString()
        ))->where('extendedProps.kind', 'leave')->values();

        $this->assertCount(1, $leaves);
        $this->assertSame('On leave', $leaves->first()['title']);
        $this->assertTrue($leaves->first()['allDay']);
        $this->assertSame($start, substr($leaves->first()['start'], 0, 10));
        $this->assertSame(
            Carbon::parse($end)->addDay()->toDateString(),
            substr($leaves->first()['end'], 0, 10)
        );
        $this->assertSame('leave-'.$approved->id, (string) $leaves->first()['id']);
    }
}
