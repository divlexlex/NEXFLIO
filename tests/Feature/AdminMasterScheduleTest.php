<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\LeaveRequest;
use App\Models\Payment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_shift_times_shown_without_shift_label(): void
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

        $this->assertStringContainsString('10:00 AM – 4:00 PM', $html);
        $this->assertStringNotContainsString('AM ·', $html);
        $this->assertStringNotContainsString('PM ·', $html);
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

    public function test_no_cell_selection_links_panel_or_highlight(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->addDays(14)->toDateString());
        $date = Carbon::parse($weekStart)->addDays(3)->toDateString();

        $appointment = $this->makeAppointment($this->staff, $date);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.master-schedule', ['week' => $weekStart]))
            ->assertOk()
            ->getContent();

        // No cell-selection links (no date-selection params anywhere).
        $this->assertStringNotContainsString('date=', $html);
        // No selected-cell highlight.
        $this->assertStringNotContainsString('table-warning', $html);
        // No detail panel markup or hint copy.
        $this->assertStringNotContainsString("Select a cell to see that day's appointments.", $html);
        $this->assertStringNotContainsString('No appointments that day.', $html);
        // The hover card still renders this appointment's details.
        $this->assertStringContainsString('nx-ms-hovercard', $html);
        $this->assertStringContainsString($appointment->referenceCode(), $html);
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

    public function test_hover_card_shows_details_money_and_no_interactive_elements(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->addDays(14)->toDateString());
        $saturday = Carbon::parse($weekStart)->addDays(6)->toDateString();

        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'variant_label' => 'Couple',
            'booked_price' => 3699,
            'personnel_id' => $this->staff->id,
            'appointment_date' => $saturday,
            'start_time' => '14:00:00',
            'status' => AppointmentStatus::Completed,
        ]);
        Payment::create([
            'appointment_id' => $appointment->id,
            'kind' => 'payment',
            'amount' => 500,
            'method' => 'gcash',
            'status' => PaymentStatus::Verified,
            'verified_by' => $this->manager->id,
            'verified_at' => now(),
        ]);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.master-schedule', ['week' => $weekStart]))
            ->assertOk()
            ->getContent();

        // Saturday column flips the card to the left.
        $this->assertStringContainsString('nx-ms-hovercard-flip', $html);

        $card = Str::between($html, '<div class="nx-ms-hovercard', 'Balance');
        $this->assertStringContainsString($appointment->referenceCode(), $card);
        $this->assertStringContainsString($this->service->name.' (Couple)', $card);
        $this->assertStringContainsString($this->client->name, $card);
        $this->assertStringContainsString($this->staff->fullName(), $card);
        $this->assertStringContainsString('Completed', $card);
        $this->assertStringContainsString('₱3,699.00', $card);
        $this->assertStringContainsString('₱500.00', $card);
        $this->assertStringContainsString('₱3,199.00', $html);

        // View-only: no links, buttons, or forms inside the card.
        $this->assertStringNotContainsString('<a', $card);
        $this->assertStringNotContainsString('<button', $card);
        $this->assertStringNotContainsString('<form', $card);
    }

    public function test_flip_modifier_only_on_friday_and_saturday_columns(): void
    {
        $weekStart = StaffShiftAssignment::weekStartOf(now()->addDays(14)->toDateString());

        foreach ([1, 5, 6] as $offset) {
            Appointment::create([
                'user_id' => $this->client->id,
                'service_id' => $this->service->id,
                'booked_price' => 500,
                'personnel_id' => $this->staff->id,
                'appointment_date' => Carbon::parse($weekStart)->addDays($offset)->toDateString(),
                'start_time' => '10:00:00',
                'status' => AppointmentStatus::Booked,
            ]);
        }

        $html = $this->actingAs($this->manager)
            ->get(route('admin.master-schedule', ['week' => $weekStart]))
            ->assertOk()
            ->getContent();

        // Monday plain, Friday + Saturday flipped (the CSS rule itself also
        // mentions the class name, so count the markup attribute form).
        $this->assertSame(2, substr_count($html, 'nx-ms-hovercard-flip"'));
    }
}
