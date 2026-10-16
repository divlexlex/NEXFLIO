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
 * Step 2A remnants — reason-dropdown rework, amount received, and the
 * LEGACY requested-refund settlement path (markRefundSent). The client
 * refund-request flow for rejected bookings is gone: outcomes are now
 * top-up (insufficient_amount), resubmit-proof (invalid_proof), or final.
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
        foreach (['Insufficient amount sent', 'Proof is invalid or unreadable',
            'Payment sent to a wrong account', 'No payment received', 'Other'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertStringNotContainsString('Duplicate booking', $html);
        $this->assertStringContainsString('id="reject-custom-wrap" class="d-none', $html);
        $this->assertStringContainsString('>Custom reason</label>', $html);
        $this->assertStringContainsString('id="reject-amount-wrap" class="d-none', $html);
        $this->assertStringContainsString('Amount actually received', $html);
        $this->assertStringNotContainsString('refund_eligible', $html);
        $this->assertStringContainsString('topup_eligible', $html);
    }

    // (b) Legacy requested refunds still settle: reject with amount, then a
    // pre-existing 'requested' row (legacy in-flight or cancellation path)
    // is marked sent + mailed. The client request endpoint is gone.
    public function test_legacy_requested_refund_can_be_marked_sent(): void
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

        // The client refund-request route no longer exists...
        $this->actingAs($this->client)->post('/account/bookings/'.$appointment->id.'/refund')
            ->assertNotFound();

        // ...but a legacy in-flight request still settles + mails.
        $appointment->payment->update([
            'refund_status' => 'requested',
            'refund_requested_at' => now(),
        ]);

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
            ->assertDontSee('Request refund');

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

        // The removed refund endpoint 404s instead of erroring.
        $this->actingAs($this->client)->post('/account/bookings/'.$appointment->id.'/refund')
            ->assertNotFound();
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

    // Guards: unknown categories are rejected by validation, the removed
    // refund route 404s, and mark-sent on a non-requested payment refused.
    public function test_reject_and_legacy_refund_guards(): void
    {
        $appointment = $this->pendingBooking();

        $this->actingAs($this->manager)->patch(
            route('admin.payments.reject', $appointment->payment->id),
            ['rejection_category' => 'duplicate_booking', 'amount_received' => 600]
        )->assertSessionHasErrors('rejection_category');

        // The removed client refund route is gone entirely.
        $this->actingAs($this->client)->post('/account/bookings/'.$appointment->id.'/refund')
            ->assertNotFound();

        // Mark-sent on a non-requested payment refused.
        $fresh = $this->pendingBooking();
        $this->actingAs($this->manager)->patch(route('admin.payments.refund-sent', $fresh->payment->id))
            ->assertSessionHasErrors('refund');
    }
}
