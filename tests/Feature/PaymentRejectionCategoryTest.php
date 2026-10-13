<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Mail\PaymentRejectedMail;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Payment rejection categories — structured category stored alongside the
 * manager's free-text note, shown to the client with the "Payment rejected"
 * state. Rejecting still cancels the booking exactly as before; no
 * refund/top-up flows exist yet.
 */
class PaymentRejectionCategoryTest extends TestCase
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

    private function reject(Appointment $appointment, array $payload): void
    {
        $this->actingAs($this->manager)
            ->patch(route('admin.payments.reject', $appointment->payment->id), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    // A predefined reason stores the category with NO free text (custom text
    // exists only for "Other"); eligible categories record the amount seen.
    public function test_reject_stores_category_and_amount_without_free_text(): void
    {
        $appointment = $this->pendingBooking();

        $this->reject($appointment, [
            'rejection_category' => 'insufficient_amount',
            'amount_received' => 200,
        ]);

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        $this->assertSame(PaymentStatus::Rejected, $appointment->payment->status);
        $this->assertSame('insufficient_amount', $appointment->payment->rejection_category);
        $this->assertNull($appointment->payment->rejection_reason);
        $this->assertEquals(200, (float) $appointment->payment->amount_received);
        $this->assertSame('Insufficient amount sent', $appointment->payment->rejectionCategoryLabel());
    }

    public function test_reject_requires_a_valid_category(): void
    {
        $appointment = $this->pendingBooking();

        $this->actingAs($this->manager)
            ->patch(route('admin.payments.reject', $appointment->payment->id), [
                'rejection_reason' => 'payment did not meet the booking fee',
            ])
            ->assertSessionHasErrors('rejection_category');

        $this->actingAs($this->manager)
            ->patch(route('admin.payments.reject', $appointment->payment->id), [
                'rejection_category' => 'refund-me',
                'rejection_reason' => 'payment did not meet the booking fee',
            ])
            ->assertSessionHasErrors('rejection_category');

        // Nothing was rejected by the failed attempts.
        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Unverified, $appointment->status);
        $this->assertSame(PaymentStatus::Pending, $appointment->payment->status);
    }

    // My Bookings shows the label-only line with the fixed
    // "Payment rejected" state (not "Awaiting payment verification").
    public function test_client_bookings_show_category_note_and_rejected_state(): void
    {
        $appointment = $this->pendingBooking();

        $this->reject($appointment, [
            'rejection_category' => 'insufficient_amount',
            'amount_received' => 200,
        ]);

        $this->assertSame('Payment rejected', $appointment->refresh()->paymentState());

        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Payment rejected — Insufficient amount sent')
            ->assertDontSee('Awaiting payment verification');
    }

    public function test_rejection_mail_carries_category_label(): void
    {
        Mail::fake();

        $appointment = $this->pendingBooking();

        $this->reject($appointment, [
            'rejection_category' => 'insufficient_amount',
            'amount_received' => 200,
        ]);

        Mail::assertQueued(
            PaymentRejectedMail::class,
            fn ($mail) => $mail->category === 'insufficient_amount'
                && in_array(
                    'Payment rejected — Insufficient amount sent',
                    $mail->content()->with['lines'],
                    true
                )
        );
    }

    // Verify flow and reason-only API cancels are unchanged (no category).
    public function test_verify_flow_and_api_cancel_unchanged(): void
    {
        $appointment = $this->pendingBooking();

        $this->actingAs($this->manager)
            ->patch(route('admin.payments.verify', $appointment->payment->id))
            ->assertRedirect();

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertSame(PaymentStatus::Verified, $appointment->payment->status);
        $this->assertSame('Paid in full', $appointment->paymentState());

        $uncategorized = $this->pendingBooking();

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/appointments/{$uncategorized->id}/status", [
                'status' => 'cancelled',
                'rejection_reason' => 'Blurry screenshot.',
            ])
            ->assertOk();

        $uncategorized->refresh();
        $this->assertSame(PaymentStatus::Rejected, $uncategorized->payment->status);
        $this->assertNull($uncategorized->payment->rejection_category);
        $this->assertNull($uncategorized->payment->rejectionCategoryLabel());
        $this->assertSame('Payment rejected', $uncategorized->paymentState());
    }
}
