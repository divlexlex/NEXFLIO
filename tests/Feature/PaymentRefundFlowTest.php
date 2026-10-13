<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Mail\RefundSentMail;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Step 2A — reason-dropdown rework, amount received, and the REFUND flow.
 * Top-up (step 2B) is intentionally absent: no top-up UI, routes, or
 * storage exist after this suite.
 */
class PaymentRefundFlowTest extends TestCase
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
            'name' => 'Foot Spa (test)', 'category' => 'Massage', 'description' => 'Test',
            'price' => 600, 'duration_minutes' => 45, 'status' => 'active',
            'service_location_type' => 'branch',
        ]);
    }

    private function pendingBooking(): Appointment
    {
        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'status' => AppointmentStatus::Unverified,
        ]);
        $appointment->payment()->create([
            'amount' => 600,
            'method' => 'gcash',
            'proof_path' => 'payment_proofs/test.jpg',
            'status' => PaymentStatus::Pending,
        ]);

        return $appointment;
    }

    // (a) Reject modal: reason picker, hidden custom box, hidden amount box,
    // flags rendered for the JS toggles.
    public function test_reject_modal_has_reason_picker_and_hidden_boxes(): void
    {
        $html = $this->actingAs($this->manager)->get(route('admin.payments'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('>Reason</label>', $html);
        $this->assertStringContainsString('Choose a reason…', $html);
        foreach (['Insufficient amount sent', 'Duplicate booking', 'Proof is invalid or unreadable',
            'Payment sent to a wrong account', 'No payment received', 'Other'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertStringContainsString('id="reject-custom-wrap" class="d-none', $html);
        $this->assertStringContainsString('>Custom reason</label>', $html);
        $this->assertStringContainsString('id="reject-amount-wrap" class="d-none', $html);
        $this->assertStringContainsString('Amount actually received', $html);
        $this->assertStringContainsString('refund_eligible', $html);
        $this->assertStringContainsString('topup_eligible', $html);
    }

    // (b) Full refund arc: reject with amount -> request -> sent + mailed.
    public function test_full_refund_flow(): void
    {
        Mail::fake();
        $appointment = $this->pendingBooking();

        $this->actingAs($this->manager)->patch(
            route('admin.payments.reject', $appointment->payment->id),
            ['rejection_category' => 'insufficient_amount', 'amount_received' => 200]
        )->assertRedirect()->assertSessionHasNoErrors();

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        $this->assertNull($appointment->payment->rejection_reason);
        $this->assertEquals(200, (float) $appointment->payment->amount_received);

        // Client card: label-only line (no colon text) + refund button.
        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Payment rejected — Insufficient amount sent')
            ->assertDontSee('Insufficient amount sent:')
            ->assertSee('Request refund of ₱200.00');

        // Client requests the refund.
        $this->actingAs($this->client)->post(route('account.bookings.refund', $appointment->id))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('requested', $appointment->payment->refresh()->refund_status);
        $this->assertNotNull($appointment->payment->refund_requested_at);

        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertDontSee('Request refund of')
            ->assertSee('Refund requested — the branch will send ₱200.00 back via your payment method.');

        // Admin marks it sent; client is mailed.
        $this->actingAs($this->manager)->patch(route('admin.payments.refund-sent', $appointment->payment->id))
            ->assertRedirect()->assertSessionHasNoErrors();

        $payment = $appointment->payment->refresh();
        $this->assertSame('sent', $payment->refund_status);
        $this->assertNotNull($payment->refund_sent_at);
        $this->assertSame($this->manager->id, $payment->refund_sent_by);

        Mail::assertQueued(RefundSentMail::class, fn ($mail) => str_contains(
            $mail->content()->with['lines'][1],
            "₱200.00 refund for booking {$appointment->referenceCode()} has been sent back"
        ));

        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee("Refund sent (₱200.00) on {$payment->refund_sent_at->format('M j, Y')}.");

        // Appointment status never moved past the rejection cancel.
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
    }

    // (c) "Other" needs custom text; with text the card appends it.
    public function test_other_requires_custom_text_and_shows_it(): void
    {
        $appointment = $this->pendingBooking();

        $this->actingAs($this->manager)->patch(
            route('admin.payments.reject', $appointment->payment->id),
            ['rejection_category' => 'other']
        )->assertSessionHasErrors('rejection_reason');
        $this->assertSame(AppointmentStatus::Unverified, $appointment->refresh()->status);

        $this->actingAs($this->manager)->patch(
            route('admin.payments.reject', $appointment->payment->id),
            ['rejection_category' => 'other', 'rejection_reason' => 'Client paid twice by accident']
        )->assertRedirect()->assertSessionHasNoErrors();

        $appointment->refresh();
        $this->assertSame('other', $appointment->payment->rejection_category);
        $this->assertSame('Client paid twice by accident', $appointment->payment->rejection_reason);

        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Payment rejected — Other: Client paid twice by accident');
    }

    // (d) Non-eligible reasons: no amount needed, none stored, no refund UI.
    public function test_ineligible_category_has_no_amount_or_refund_ui(): void
    {
        $appointment = $this->pendingBooking();

        $this->actingAs($this->manager)->patch(
            route('admin.payments.reject', $appointment->payment->id),
            ['rejection_category' => 'no_payment', 'amount_received' => 100]
        )->assertRedirect()->assertSessionHasNoErrors();

        $appointment->refresh();
        $this->assertSame('no_payment', $appointment->payment->rejection_category);
        $this->assertNull($appointment->payment->amount_received);

        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Payment rejected — No payment received')
            ->assertDontSee('Request refund');

        // And a stray refund request is refused server-side.
        $this->actingAs($this->client)->post(route('account.bookings.refund', $appointment->id))
            ->assertSessionHasErrors('refund');
        $this->assertNull($appointment->payment->refresh()->refund_status);
    }

    // (e) The received amount can never exceed what was charged.
    public function test_amount_received_capped_at_payment_amount(): void
    {
        $appointment = $this->pendingBooking();

        $this->actingAs($this->manager)->patch(
            route('admin.payments.reject', $appointment->payment->id),
            ['rejection_category' => 'insufficient_amount', 'amount_received' => 601]
        )->assertSessionHasErrors('amount_received');
        $this->assertSame(AppointmentStatus::Unverified, $appointment->refresh()->status);

        // Zero is allowed (nothing to refund — so no refund UI either).
        $this->actingAs($this->manager)->patch(
            route('admin.payments.reject', $appointment->payment->id),
            ['rejection_category' => 'insufficient_amount', 'amount_received' => 0]
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertDontSee('Request refund');
    }

    // Guards: strangers, double-requests, and wrong states are refused.
    public function test_refund_guards(): void
    {
        $appointment = $this->pendingBooking();

        $this->actingAs($this->manager)->patch(
            route('admin.payments.reject', $appointment->payment->id),
            ['rejection_category' => 'duplicate_booking', 'amount_received' => 600]
        )->assertRedirect();

        // Another client cannot request on someone else's booking.
        $stranger = User::create([
            'name' => 'Stranger', 'email' => 'stranger@nexflio.test',
            'password' => bcrypt('password'), 'role_id' => User::ROLE_CLIENT,
        ]);
        $this->actingAs($stranger)->post(route('account.bookings.refund', $appointment->id))
            ->assertSessionHasErrors('refund');

        // Double request refused.
        $this->actingAs($this->client)->post(route('account.bookings.refund', $appointment->id))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->client)->post(route('account.bookings.refund', $appointment->id))
            ->assertSessionHasErrors('refund');

        // Mark-sent on a non-requested payment refused.
        $fresh = $this->pendingBooking();
        $this->actingAs($this->manager)->patch(route('admin.payments.refund-sent', $fresh->payment->id))
            ->assertSessionHasErrors('refund');
    }
}
