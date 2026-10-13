<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAppointmentCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;

    private User $staff;

    private User $client;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->service = Service::create([
            'name' => 'Gel Manicure', 'category' => 'Nails', 'price' => 850,
            'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
    }

    private function booking(AppointmentStatus $status): Appointment
    {
        $appt = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'status' => $status,
            'notes' => 'Prefers soft pink.',
        ]);
        Payment::create([
            'appointment_id' => $appt->id,
            'amount' => $this->service->price,
            'method' => 'gcash',
            'status' => $status === AppointmentStatus::Unverified ? PaymentStatus::Pending : PaymentStatus::Verified,
        ]);

        return $appt;
    }

    public function test_feed_carries_drawer_details_in_extended_props(): void
    {
        $appt = $this->booking(AppointmentStatus::Unverified);

        $data = $this->actingAs($this->manager)->getJson(route('admin.appointments.feed'))->assertOk()->json();
        $event = collect($data)->firstWhere('id', $appt->id);

        $this->assertNotNull($event);
        $this->assertcontains('nx-evt-unverified', $event['classNames']);
        $p = $event['extendedProps'];
        $this->assertSame($this->client->name, $p['client']);
        $this->assertSame('Gel Manicure', $p['service']);
        $this->assertSame('Online Booking', $p['bookingType']);
        $this->assertSame('Prefers soft pink.', $p['notes']);
        $this->assertNotNull($p['payment']);
        // Money decisions happen in Payments: an Unverified booking with a
        // pending payment offers no drawer status actions, just the lock flag.
        $this->assertSame([], $p['actions']);
        $this->assertTrue($p['verificationLocked']);
    }

    public function test_walk_in_shows_as_walk_in_type(): void
    {
        $appt = Appointment::create([
            'user_id' => null, 'walk_in_name' => 'Counter Guest', 'walk_in_phone' => '0917',
            'service_id' => $this->service->id, 'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(), 'start_time' => '13:00',
            'status' => AppointmentStatus::Booked,
        ]);

        $data = $this->actingAs($this->manager)->getJson(route('admin.appointments.feed'))->json();
        $event = collect($data)->firstWhere('id', $appt->id);

        $this->assertSame('Walk-in', $event['extendedProps']['bookingType']);
        $this->assertSame('Counter Guest', $event['extendedProps']['client']);
    }

    // The drawer/web endpoint refuses to confirm or cancel an Unverified
    // booking with a pending payment — Payments verify/reject are the only
    // doors for the verification step.
    public function test_drawer_transitions_refused_for_pending_payment(): void
    {
        $appt = $this->booking(AppointmentStatus::Unverified);

        foreach (['booked', 'cancelled'] as $target) {
            $this->actingAs($this->manager)
                ->from(route('admin.appointments'))
                ->patch(route('admin.appointments.status', $appt), ['status' => $target])
                ->assertSessionHasErrors('status');
        }

        $this->assertSame(AppointmentStatus::Unverified, $appt->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $appt->fresh()->payment->status);
    }

    public function test_manager_cancels_from_the_drawer(): void
    {
        $appt = $this->booking(AppointmentStatus::Booked);

        $this->actingAs($this->manager)
            ->patch(route('admin.appointments.status', $appt), ['status' => 'cancelled'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(AppointmentStatus::Cancelled, $appt->fresh()->status);
    }

    public function test_illegal_transition_is_rejected_and_status_unchanged(): void
    {
        $appt = $this->booking(AppointmentStatus::Unverified);

        $this->actingAs($this->manager)
            ->from(route('admin.appointments'))
            ->patch(route('admin.appointments.status', $appt), ['status' => 'completed'])
            ->assertSessionHasErrors();

        $this->assertSame(AppointmentStatus::Unverified, $appt->fresh()->status);
    }

    public function test_appointments_page_renders_legend_and_drawer(): void
    {
        $this->actingAs($this->manager)->get(route('admin.appointments'))
            ->assertOk()
            ->assertSee('id="apptDrawer"', false)
            ->assertSee('Appointment Details')
            ->assertSee('Confirmed')
            ->assertSee('id="dayModal"', false);
    }

    public function test_client_cannot_hit_the_status_endpoint(): void
    {
        $appt = $this->booking(AppointmentStatus::Booked);
        $this->actingAs($this->client)
            ->patch(route('admin.appointments.status', $appt), ['status' => 'cancelled'])
            ->assertForbidden();
    }
}
