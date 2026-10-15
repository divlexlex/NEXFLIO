<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Notification;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Client dashboard (Design C, chunk 2): no notifications/calendar panels in
 * the page body — notifications live in the navbar bell, the calendar lives
 * in a pop-up modal opened from the upcoming card's "View calendar" button.
 */
class ClientDashboardCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    private User $staff;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->service = Service::where('status', 'active')->firstOrFail();
    }

    private function makeAppointment(User $client, AppointmentStatus $status, string $date): Appointment
    {
        return Appointment::create([
            'user_id' => $client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => $date,
            'start_time' => '14:00:00',
            'status' => $status,
        ]);
    }

    private function freshClient(): User
    {
        return User::create([
            'name' => 'Fresh Client',
            'email' => 'fresh.client@nexflio.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_CLIENT,
        ]);
    }

    public function test_body_has_no_panels_only_bell_and_modal(): void
    {
        Notification::create([
            'user_id' => $this->client->id,
            'title' => 'Panel Gone',
            'body' => 'The old panel must not render this.',
        ]);

        $html = $this->actingAs($this->client)->get(route('account.dashboard'))->assertOk()->getContent();

        // The navbar bell is now the only notifications UI: one "Mark all
        // read" (its header form), one per-item "Mark read", and no old
        // "N new" badge anywhere.
        $this->assertSame(1, substr_count($html, 'Mark all read'));
        $this->assertSame(1, substr_count($html, 'Mark read'));
        $this->assertStringNotContainsString(' new</span>', $html);

        // The calendar lives only in the modal now.
        $this->assertStringContainsString('id="calendarModal"', $html);
        $this->assertSame(1, substr_count($html, 'Your Appointments Calendar'));
    }

    public function test_modal_contains_current_month_calendar(): void
    {
        $html = $this->actingAs($this->client)->get(route('account.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('id="calendarModal"', $html);
        $this->assertStringContainsString(now()->format('F Y'), $html);
        $this->assertStringContainsString('nx-calendar', $html);
    }

    public function test_view_calendar_button_present_with_upcoming(): void
    {
        $this->makeAppointment($this->client, AppointmentStatus::Booked, now()->addDays(3)->toDateString());

        $html = $this->actingAs($this->client)->get(route('account.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString($this->service->name, $html);
        $this->assertStringContainsString('View calendar', $html);
        $this->assertStringContainsString('data-bs-target="#calendarModal"', $html);
    }

    public function test_view_calendar_button_present_without_upcoming(): void
    {
        $html = $this->actingAs($this->freshClient())->get(route('account.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('No upcoming appointments.', $html);
        $this->assertStringContainsString('View calendar', $html);
        $this->assertStringContainsString('data-bs-target="#calendarModal"', $html);
    }

    public function test_month_param_renders_that_month_inside_modal(): void
    {
        $month = now()->subMonth()->format('Y-m');

        $html = $this->actingAs($this->client)
            ->get(route('account.dashboard', ['month' => $month, 'calendar' => 1]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="calendarModal"', $html);
        $this->assertStringContainsString(now()->subMonth()->format('F Y'), $html);
    }

    public function test_upcoming_history_and_suggestions_still_render(): void
    {
        $this->makeAppointment($this->client, AppointmentStatus::Booked, now()->addDays(3)->toDateString());
        $this->makeAppointment($this->client, AppointmentStatus::Completed, now()->subDays(3)->toDateString());

        $html = $this->actingAs($this->client)->get(route('account.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Upcoming Appointment', $html);
        $this->assertStringContainsString('Recent Service History', $html);
        $this->assertStringContainsString('Suggested for You', $html);
    }

    public function test_quick_action_tiles_replaced_by_single_header_book_button(): void
    {
        $html = $this->actingAs($this->client)->get(route('account.dashboard'))->assertOk()->getContent();

        // The tile row is gone — the three duplicate destinations no longer
        // render as tiles.
        $this->assertStringNotContainsString('nx-quick-action', $html);
        $this->assertStringNotContainsString('Browse Services', $html);
        $this->assertStringNotContainsString('My Bookings', $html);
        $this->assertStringNotContainsString('My Offers', $html);

        // The welcome header holds exactly one "Book an Appointment" button
        // reusing the old tile's href + service-picker modal trigger verbatim.
        $header = Str::between($html, '<header class="nx-hero">', '</header>');
        $this->assertSame(1, substr_count($header, 'Book an Appointment'));
        $this->assertStringContainsString('href="'.route('account.booking.start').'"', $header);
        $this->assertStringContainsString('data-bs-target="#bookServiceModal"', $header);
        $this->assertStringContainsString('bi-calendar-plus', $header);
    }
}
