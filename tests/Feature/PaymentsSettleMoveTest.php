<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Money decisions live in Payments; Appointments is operations-only.
 * Unverified bookings with a pending payment offer no drawer Confirm /
 * Reject (server refuses direct POSTs too); branch settlement moved from
 * the drawer onto Payments cards prefilled with the balance due.
 */
class PaymentsSettleMoveTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    private User $manager;

    private Service $sam;

    private int $samCoupleId;

    private Service $manicure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        // Real seeded catalog: SAM Couple 4399, fee tier Couple 500.
        $this->sam = Service::where('name', 'Package SAM')->firstOrFail();
        $this->samCoupleId = $this->sam->variants()->where('label', 'Couple')->firstOrFail()->id;
        $this->manicure = Service::where('name', 'Manicure')->firstOrFail();
    }

    private function samBooking(): Appointment
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            [
                'service_id' => $this->sam->id,
                'variant_id' => $this->samCoupleId,
                'personnel_id' => 'any',
                'appointment_date' => now()->addDay()->toDateString(),
                'start_time' => '10:00',
                'method' => 'gcash',
                'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            ]
        )->assertRedirect();

        return Appointment::latest('id')->firstOrFail();
    }

    private function feedEvent(int $appointmentId): array
    {
        $data = $this->actingAs($this->manager)->getJson(route('admin.appointments.feed'))
            ->assertOk()->json();

        $event = collect($data)->firstWhere('id', $appointmentId);
        $this->assertNotNull($event);

        return $event;
    }

    // (a) Unverified + pending: no drawer Confirm/Reject, hint shown, direct
    // POSTs of either transition refused.
    public function test_locked_booking_has_no_drawer_actions_and_refuses_posts(): void
    {
        $appointment = $this->samBooking();

        $event = $this->feedEvent($appointment->id);
        $this->assertSame([], $event['extendedProps']['actions']);
        $this->assertTrue($event['extendedProps']['verificationLocked']);

        $this->actingAs($this->manager)->get(route('admin.appointments'))
            ->assertOk()
            ->assertSee('Payment pending — verify or reject it in', false);

        foreach (['booked', 'cancelled'] as $target) {
            $this->actingAs($this->manager)
                ->from(route('admin.appointments'))
                ->patch(route('admin.appointments.status', $appointment->id), ['status' => $target])
                ->assertSessionHasErrors('status');
        }

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Unverified, $appointment->status);
        $this->assertSame(PaymentStatus::Pending, $appointment->payment->status);
    }

    // (b) After Payments verification: operations return, settle section gone.
    public function test_verified_booking_regains_operations_without_settle(): void
    {
        $appointment = $this->samBooking();

        $this->actingAs($this->manager)
            ->patch(route('admin.payments.verify', $appointment->payment->id))
            ->assertRedirect();
        $this->assertSame(AppointmentStatus::Booked, $appointment->refresh()->status);

        $event = $this->feedEvent($appointment->id);
        $this->assertFalse($event['extendedProps']['verificationLocked']);
        // No preferred personnel, so no "Start service" — no-show + cancel.
        $this->assertSame(['no-show', 'cancelled'], array_column($event['extendedProps']['actions'], 'to'));

        $this->actingAs($this->manager)->get(route('admin.appointments'))
            ->assertOk()
            ->assertDontSee('dw-settle-row', false)
            ->assertDontSee('dw-settleForm', false)
            ->assertDontSee('Record branch payment');
    }

    // (c) Verified-tab card carries the settle form prefilled with the
    // balance (3899); recording it flips the card to Paid in full.
    public function test_settle_form_prefilled_with_balance_then_paid_in_full(): void
    {
        $appointment = $this->samBooking();

        $this->actingAs($this->manager)
            ->patch(route('admin.payments.verify', $appointment->payment->id))
            ->assertRedirect();

        $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'verified']))
            ->assertOk()
            ->assertSee('Record branch payment')
            ->assertSee('value="3899.00"', false);

        $this->actingAs($this->manager)->post(
            route('admin.appointments.branch-payment', $appointment->id),
            ['amount' => 3899, 'method' => 'cash']
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Paid in full', $appointment->refresh()->paymentState());

        $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all']))
            ->assertOk()
            ->assertSee('Paid in full');
    }

    // (d) Cash booking: created Unverified (a request), confirmable from the
    // drawer, then settle recordable on the All-tab card (cash primaries
    // list nowhere else).
    public function test_cash_booking_settles_from_all_tab(): void
    {
        $this->actingAs($this->client)->post(route('account.booking.branch.store'), [
            'service_id' => $this->manicure->id,
            'personnel_id' => 'any',
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'method' => 'cash',
            'guests' => 1,
        ])->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertSame(AppointmentStatus::Unverified, $appointment->status);

        // Cash carries no online payment to verify: drawer stays actionable.
        $event = $this->feedEvent($appointment->id);
        $this->assertFalse($event['extendedProps']['verificationLocked']);
        $this->assertSame(['booked', 'cancelled'], array_column($event['extendedProps']['actions'], 'to'));

        $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'pending']))
            ->assertOk()
            ->assertDontSee('Manicure');

        // Manager confirms the Cash request from the Appointments side.
        $this->actingAs($this->manager)->patch(
            route('admin.appointments.status', $appointment->id),
            ['status' => AppointmentStatus::Booked->value]
        )->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(AppointmentStatus::Booked, $appointment->refresh()->status);

        $event = $this->feedEvent($appointment->id);
        $this->assertFalse($event['extendedProps']['verificationLocked']);
        // No preferred personnel, so no "Start service" — no-show + cancel.
        $this->assertSame(['no-show', 'cancelled'], array_column($event['extendedProps']['actions'], 'to'));

        $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'pending']))
            ->assertOk()
            ->assertDontSee('Manicure');

        $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all']))
            ->assertOk()
            ->assertSee('Record branch payment')
            ->assertSee('value="179.00"', false);

        $this->actingAs($this->manager)->post(
            route('admin.appointments.branch-payment', $appointment->id),
            ['amount' => 179, 'method' => 'cash']
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Paid in full', $appointment->refresh()->paymentState());
    }

    // Header holds ONE status dropdown (Pending default) plus the Amounts
    // select — no nav-pill tabs.
    public function test_header_dropdown_with_pending_default(): void
    {
        $html = $this->actingAs($this->manager)->get(route('admin.payments'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<ul class="nav nav-pills', $html);
        $this->assertStringContainsString('id="status-filter"', $html);
        foreach (['pending', 'verified', 'rejected', 'all', 'refund', 'no_refund'] as $value) {
            $this->assertStringContainsString('value="'.$value.'"', $html);
        }
        $this->assertMatchesRegularExpression('/<option value="pending"[^>]*selected/', $html);
        $this->assertStringContainsString('id="balance-filter"', $html);
    }

    // Balance filter cooperates with tabs: due shows only balance > 0,
    // paid shows only settled ones.
    public function test_balance_filter(): void
    {
        Storage::fake('public');

        $sam = $this->samBooking();
        $this->actingAs($this->manager)
            ->patch(route('admin.payments.verify', $sam->payment->id))
            ->assertRedirect();

        // Online Manicure: 179 price -> 89.50 fee; verify, then settle the
        // remainder — the settled counterpart.
        $this->actingAs($this->client)->post(route('account.booking.branch.store'), [
            'service_id' => $this->manicure->id,
            'personnel_id' => 'any',
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '11:00',
            'method' => 'gcash',
            'guests' => 1,
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertRedirect();
        $settled = Appointment::latest('id')->firstOrFail();
        $this->actingAs($this->manager)
            ->patch(route('admin.payments.verify', $settled->payment->id))
            ->assertRedirect();
        $this->actingAs($this->manager)->post(
            route('admin.appointments.branch-payment', $settled->id),
            ['amount' => 89.50, 'method' => 'cash']
        )->assertRedirect()->assertSessionHasNoErrors();

        // SAM owes 3899 (verified fee, open balance); Manicure is settled.
        $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all', 'balance' => 'due']))
            ->assertOk()
            ->assertSee('Package SAM')
            ->assertSee('value="3899.00"', false)
            ->assertDontSee('Manicure');

        $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all', 'balance' => 'paid']))
            ->assertOk()
            ->assertSee('Manicure')
            ->assertDontSee('Package SAM');

        // Both dropdowns reflect the active filters.
        $html = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'verified', 'balance' => 'due']))
            ->assertOk()
            ->assertSee('Package SAM')
            ->getContent();

        $this->assertMatchesRegularExpression('/<option value="verified"[^>]*selected/', $html);
        $this->assertMatchesRegularExpression('/<option value="due"[^>]*selected/', $html);
    }
}
