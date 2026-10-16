<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\Payment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Manager-side UX: drawer no-personnel warning + no-show/cancel
 * confirmations, Payments check-out sequencing + record confirmation,
 * and per-session admin filter persistence.
 */
class ManagerUxFixesTest extends TestCase
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
            'name' => 'Manager UX Facial', 'category' => 'Facial', 'price' => 800,
            'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        StaffProfile::updateOrCreate(
            ['user_id' => $this->staff->id],
            ['position' => 'Technician', 'employment_status' => 'active', 'commission_rate' => 10]
        );
    }

    private function booking(AppointmentStatus $status, ?int $personnelId, float $verifiedPaid = 0): Appointment
    {
        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $personnelId,
            'appointment_date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00',
            'status' => $status,
            'booked_price' => 800,
        ]);
        if ($verifiedPaid > 0) {
            Payment::create([
                'appointment_id' => $appointment->id,
                'amount' => $verifiedPaid,
                'method' => 'gcash',
                'status' => PaymentStatus::Verified,
                'verified_by' => $this->manager->id,
                'verified_at' => now(),
            ]);
        }

        return $appointment;
    }

    private function feedEvent(int $appointmentId): array
    {
        $data = $this->actingAs($this->manager)->getJson(route('admin.appointments.feed'))->assertOk()->json();
        $event = collect($data)->firstWhere('id', $appointmentId);
        $this->assertNotNull($event);

        return $event;
    }

    // ---- Part 1: no-personnel warning ----

    public function test_drawer_shows_no_personnel_warning_without_assignment(): void
    {
        $appointment = $this->booking(AppointmentStatus::Booked, null, 800);

        $event = $this->feedEvent($appointment->id);
        $this->assertNull($event['extendedProps']['personnelId']);
        $this->assertFalse($event['extendedProps']['isTerminal']);

        $html = $this->actingAs($this->manager)->get(route('admin.appointments'))->assertOk()->getContent();

        $this->assertStringContainsString('id="dw-staff-notice"', $html);
        $this->assertStringContainsString(
            'No personnel assigned — assign a staff member before this service can be started.',
            $html
        );
        // Rendered hidden by default; the drawer script reveals it when the
        // open appointment has no personnel and is still actionable.
        $this->assertStringContainsString('!p.personnelId && !p.isTerminal', $html);
    }

    public function test_drawer_warning_hidden_when_personnel_assigned(): void
    {
        $appointment = $this->booking(AppointmentStatus::Booked, $this->staff->id, 800);

        $event = $this->feedEvent($appointment->id);
        $this->assertSame($this->staff->id, $event['extendedProps']['personnelId']);
    }

    // ---- Part 2: no-show / cancel confirmations ----

    public function test_drawer_noshow_and_cancel_open_confirmation_modals(): void
    {
        $html = $this->actingAs($this->manager)->get(route('admin.appointments'))->assertOk()->getContent();

        $this->assertStringContainsString('id="dwNoShowModal"', $html);
        $this->assertStringContainsString('Mark as no-show?', $html);
        $this->assertStringContainsString('id="dwCancelModal"', $html);
        $this->assertStringContainsString('Cancel this appointment?', $html);
        $this->assertStringContainsString('id="dw-noshow-confirm"', $html);
        $this->assertStringContainsString('id="dw-cancel-confirm"', $html);
        // Plain clicks open the modals instead of submitting …
        $this->assertStringContainsString("new bootstrap.Modal(document.getElementById('dwNoShowModal')).show()", $html);
        $this->assertStringContainsString("new bootstrap.Modal(document.getElementById('dwCancelModal')).show()", $html);
        // … while the shared form still posts to the existing endpoint.
        $this->assertStringContainsString('id="dw-statusForm"', $html);
        $this->assertStringContainsString("statusBase + '/' + event.id + '/status'", $html);
    }

    public function test_drawer_status_transitions_still_work(): void
    {
        $noshow = $this->booking(AppointmentStatus::Booked, $this->staff->id, 800);

        $this->actingAs($this->manager)->patch(
            route('admin.appointments.status', $noshow->id),
            ['status' => AppointmentStatus::NoShow->value]
        )->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(AppointmentStatus::NoShow, $noshow->fresh()->status);

        $cancel = $this->booking(AppointmentStatus::Booked, $this->staff->id, 800);

        $this->actingAs($this->manager)->patch(
            route('admin.appointments.status', $cancel->id),
            ['status' => AppointmentStatus::Cancelled->value, 'rejection_reason' => 'Client asked.']
        )->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(AppointmentStatus::Cancelled, $cancel->fresh()->status);
    }

    // ---- Part 3: check-out sequencing + record confirmation ----

    public function test_payments_card_with_balance_has_no_checkout_section(): void
    {
        $this->booking(AppointmentStatus::Booked, $this->staff->id, 300);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Record branch payment', $html);
        $this->assertStringNotContainsString('checkout', $html);
        $this->assertStringNotContainsString('check-out', $html);
        $this->assertStringNotContainsString('Check out', $html);
    }

    public function test_record_then_checkout_sequence_completes_without_zero_payment(): void
    {
        $appointment = $this->booking(AppointmentStatus::Booked, $this->staff->id, 300);

        $this->actingAs($this->manager)->post(
            route('admin.appointments.branch-payment', $appointment->id),
            ['amount' => 500, 'method' => 'cash']
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0.0, $appointment->refresh()->balanceDue());

        // Balance 0: the card now offers Confirm check-out.
        $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all']))
            ->assertOk()
            ->assertSee('Confirm check-out');

        $paymentsBefore = $appointment->payments()->count();

        $this->actingAs($this->manager)->post(
            route('admin.appointments.checkout', $appointment->id),
            []
        )->assertRedirect()->assertSessionHasNoErrors();

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Completed, $appointment->status);
        // No ₱0 row created.
        $this->assertSame($paymentsBefore, $appointment->payments()->count());
        $this->assertSame(0, $appointment->payments()->where('amount', 0)->count());
        // Commission fired through the normal completion path.
        $this->assertDatabaseHas('commissions', [
            'appointment_id' => $appointment->id,
            'user_id' => $this->staff->id,
        ]);
    }

    public function test_record_payment_renders_confirmation_modal_with_summary(): void
    {
        $appointment = $this->booking(AppointmentStatus::Booked, $this->staff->id, 300);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="settle-form-'.$appointment->id.'"', $html);
        $this->assertStringContainsString('id="settle-confirm-'.$appointment->id.'"', $html);
        $this->assertStringContainsString('data-settle-summary', $html);
        $this->assertStringContainsString('form="settle-form-'.$appointment->id.'"', $html);
        // Summary template: "Record ₱<amount> via <method> for <client> — <service>?"
        $this->assertStringContainsString("'Record ' + amountText + ' via '", $html);
    }

    // ---- Part 4: filter persistence ----

    public function test_payments_status_filter_persists_in_session(): void
    {
        $verified = $this->booking(AppointmentStatus::Booked, $this->staff->id, 800);
        $pending = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDays(3)->toDateString(),
            'start_time' => '11:00',
            'status' => AppointmentStatus::Unverified,
            'booked_price' => 800,
        ]);
        Payment::create([
            'appointment_id' => $pending->id,
            'amount' => 300,
            'method' => 'gcash',
            'proof_path' => 'payment_proofs/test.jpg',
            'status' => PaymentStatus::Pending,
        ]);

        // Fresh session opens on the Pending default.
        $this->actingAs($this->manager)->get(route('admin.payments'))
            ->assertOk()
            ->assertSee($pending->referenceCode(), false)
            ->assertDontSee($verified->referenceCode(), false);

        // Choosing Verified stores it; reopening without params keeps it.
        $this->actingAs($this->manager)->get(route('admin.payments', ['status' => 'verified']))
            ->assertOk()
            ->assertSee($verified->referenceCode(), false);

        $this->actingAs($this->manager)->get(route('admin.payments'))
            ->assertOk()
            ->assertSee($verified->referenceCode(), false)
            ->assertDontSee($pending->referenceCode(), false);
    }

    public function test_payments_balance_filter_persists_in_session(): void
    {
        $owing = $this->booking(AppointmentStatus::Booked, $this->staff->id, 300);
        $settled = $this->booking(AppointmentStatus::Booked, $this->staff->id, 800);
        $settled->update(['start_time' => '11:00']);

        $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all', 'balance' => 'paid']))
            ->assertOk()
            ->assertSee($settled->referenceCode(), false)
            ->assertDontSee($owing->referenceCode(), false);

        $this->actingAs($this->manager)->get(route('admin.payments'))
            ->assertOk()
            ->assertSee($settled->referenceCode(), false)
            ->assertDontSee($owing->referenceCode(), false);
    }

    public function test_change_requests_status_filter_persists_in_session(): void
    {
        $pendingAppt = $this->booking(AppointmentStatus::Booked, $this->staff->id, 800);
        AppointmentChangeRequest::create([
            'appointment_id' => $pendingAppt->id, 'requested_by' => $this->client->id,
            'type' => 'cancellation', 'status' => 'pending', 'reason_category' => 'change_of_plans',
        ]);

        $approvedAppt = $this->booking(AppointmentStatus::Booked, $this->staff->id, 800);
        $approvedAppt->update(['start_time' => '11:00']);
        AppointmentChangeRequest::create([
            'appointment_id' => $approvedAppt->id, 'requested_by' => $this->client->id,
            'type' => 'cancellation', 'status' => 'approved', 'reason_category' => 'change_of_plans',
            'refund_decision' => 'non_refundable', 'reviewed_by' => $this->manager->id,
        ]);

        // Fresh session opens on the Pending default.
        $this->actingAs($this->manager)->get(route('admin.appointment-requests'))
            ->assertOk()
            ->assertSee('Manager UX Facial');

        $this->actingAs($this->manager)
            ->get(route('admin.appointment-requests', ['status' => 'approved']))
            ->assertOk();

        $html = $this->actingAs($this->manager)->get(route('admin.appointment-requests'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<option value="approved"[^>]*selected/', $html);
    }

    public function test_services_category_filter_persists_in_session(): void
    {
        Service::create([
            'name' => 'UX Test Massage', 'category' => 'Massage', 'price' => 500,
            'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        $this->actingAs($this->manager)
            ->get(route('admin.services', ['category' => 'Massage']))
            ->assertOk()
            ->assertSee('UX Test Massage')
            ->assertDontSee('Manager UX Facial');

        $this->actingAs($this->manager)->get(route('admin.services'))
            ->assertOk()
            ->assertSee('UX Test Massage')
            ->assertDontSee('Manager UX Facial');
    }

    public function test_appointments_status_filter_persists_in_session(): void
    {
        $booked = $this->booking(AppointmentStatus::Booked, $this->staff->id, 800);
        $booked->update(['appointment_date' => now()->addDays(5)->toDateString()]);
        $completed = $this->booking(AppointmentStatus::Completed, $this->staff->id, 800);
        // Distinct dates so each row is identifiable in the list HTML.
        $bookedDate = $booked->fresh()->appointment_date->format('M j, Y');
        $completedDate = $completed->fresh()->appointment_date->format('M j, Y');
        $this->assertNotSame($bookedDate, $completedDate);

        $this->actingAs($this->manager)
            ->get(route('admin.appointments', ['status' => 'completed']))
            ->assertOk();

        $html = $this->actingAs($this->manager)->get(route('admin.appointments'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<option value="completed"[^>]*selected/', $html);
        $this->assertStringContainsString($completedDate, $html);
        $this->assertStringNotContainsString($bookedDate, $html);
    }
}
