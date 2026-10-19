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
 * Final rejection outcomes — exactly three client paths: top-up for
 * insufficient_amount, resubmit-proof for invalid_proof, and final
 * rejection for everything else. No client refund flow, no
 * duplicate_booking reason.
 */
class RejectedBookingOutcomesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    private User $manager;

    private User $staff;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();

        $this->service = Service::create([
            'name' => 'Outcome Facial (test)', 'category' => 'Massage', 'description' => 'Test',
            'price' => 800, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
    }

    private function pendingBooking(): Appointment
    {
        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '12:00',
            'status' => AppointmentStatus::Unverified,
        ]);
        $appointment->payment()->create([
            'amount' => 300,
            'method' => 'gcash',
            'proof_path' => 'payment_proofs/old.jpg',
            'status' => PaymentStatus::Pending,
        ]);

        return $appointment;
    }

    private function reject(Appointment $appointment, string $category, $amount = null): void
    {
        $payload = ['rejection_category' => $category];
        if ($amount !== null) {
            $payload['amount_received'] = $amount;
        }
        if ($category === 'other') {
            $payload['rejection_reason'] = 'Test reason.';
        }

        $this->actingAs($this->manager)->patch(
            route('admin.payments.reject', $appointment->payment->id),
            $payload
        )->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_config_has_three_outcomes_and_no_duplicate(): void
    {
        $reasons = config('payment.rejection_reasons');

        $this->assertSame(
            ['insufficient_amount', 'invalid_proof', 'wrong_recipient', 'no_payment', 'other'],
            array_keys($reasons)
        );
        $this->assertTrue($reasons['insufficient_amount']['topup_eligible']);
        $this->assertArrayNotHasKey('refund_eligible', $reasons['insufficient_amount']);
        $this->assertTrue($reasons['invalid_proof']['resubmit_proof']);
        $this->assertFalse($reasons['wrong_recipient']['topup_eligible'] ?? false);
    }

    public function test_insufficient_shows_topup_only(): void
    {
        $appointment = $this->pendingBooking();
        $this->reject($appointment, 'insufficient_amount', 200);

        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Continue to book this')
            ->assertDontSee('Request refund')
            ->assertDontSee('Send a new proof');
    }

    public function test_invalid_proof_resubmit_happy_path(): void
    {
        Storage::fake('public');

        $appointment = $this->pendingBooking();
        $this->reject($appointment, 'invalid_proof');

        // Card offers only the resubmit action.
        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Your proof was rejected as invalid or unreadable')
            ->assertSee('Send a new proof')
            ->assertDontSee('Continue to book this');

        $oldProof = $appointment->payment->proof_path;

        $this->actingAs($this->client)->post(
            route('account.bookings.resubmit-proof', $appointment->id),
            ['payment_proof' => UploadedFile::fake()->image('clearer.jpg')]
        )->assertRedirect(route('account.bookings'))->assertSessionHasNoErrors();

        $payment = $appointment->payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNotSame($oldProof, $payment->proof_path);
        $this->assertStringStartsWith('payment_proofs/', $payment->proof_path);
        $this->assertNull($payment->rejection_category);
        $this->assertNull($payment->rejection_reason);
        $this->assertNull($payment->refund_status);
        $this->assertSame(1, $appointment->payments()->count());
        $this->assertSame(AppointmentStatus::Unverified, $appointment->refresh()->status);

        // Back in the Pending queue with the new proof, verifiable to Booked.
        $this->actingAs($this->manager)->get(route('admin.payments', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Outcome Facial (test)');

        $this->actingAs($this->manager)
            ->patch(route('admin.payments.verify', $payment->id))
            ->assertRedirect();
        $this->assertSame(AppointmentStatus::Booked, $appointment->refresh()->status);
    }

    public function test_resubmit_refused_for_wrong_category(): void
    {
        Storage::fake('public');

        $appointment = $this->pendingBooking();
        $this->reject($appointment, 'insufficient_amount', 200);

        $this->actingAs($this->client)->post(
            route('account.bookings.resubmit-proof', $appointment->id),
            ['payment_proof' => UploadedFile::fake()->image('clearer.jpg')]
        )->assertSessionHasErrors('resubmit');

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
    }

    public function test_resubmit_refused_for_past_appointment(): void
    {
        Storage::fake('public');

        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->subDay()->toDateString(),
            'start_time' => '10:00',
            'status' => AppointmentStatus::Cancelled,
        ]);
        $appointment->payment()->create([
            'amount' => 300,
            'method' => 'gcash',
            'proof_path' => 'payment_proofs/old.jpg',
            'status' => PaymentStatus::Rejected,
            'rejection_category' => 'invalid_proof',
        ]);

        $this->actingAs($this->client)->post(
            route('account.bookings.resubmit-proof', $appointment->id),
            ['payment_proof' => UploadedFile::fake()->image('clearer.jpg')]
        )->assertSessionHasErrors('resubmit');

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
    }

    public function test_resubmit_refused_when_slot_taken(): void
    {
        Storage::fake('public');

        $appointment = $this->pendingBooking();
        $this->reject($appointment, 'invalid_proof');

        $this->actingAs($this->manager)->post(route('admin.appointments.walk-in'), [
            'walk_in_name' => 'Slot Taker',
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '12:00',
        ])->assertRedirect();

        $this->actingAs($this->client)->post(
            route('account.bookings.resubmit-proof', $appointment->id),
            ['payment_proof' => UploadedFile::fake()->image('clearer.jpg')]
        )->assertSessionHasErrors('resubmit');

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
    }

    public function test_resubmit_404_for_other_clients_appointment(): void
    {
        Storage::fake('public');

        $stranger = User::create([
            'name' => 'Stranger', 'email' => 'stranger@nexflio.test',
            'password' => bcrypt('password'), 'role_id' => User::ROLE_CLIENT,
        ]);

        $appointment = $this->pendingBooking();
        $this->reject($appointment, 'invalid_proof');

        $this->actingAs($stranger)->post(
            route('account.bookings.resubmit-proof', $appointment->id),
            ['payment_proof' => UploadedFile::fake()->image('clearer.jpg')]
        )->assertNotFound();
    }

    public function test_final_categories_show_no_buttons(): void
    {
        foreach (['wrong_recipient', 'no_payment', 'other'] as $category) {
            $appointment = $this->pendingBooking();
            $this->reject($appointment, $category);

            $this->actingAs($this->client)->get(route('account.bookings'))
                ->assertOk()
                ->assertSee('Payment rejected')
                ->assertDontSee('Continue to book this')
                ->assertDontSee('Send a new proof')
                ->assertDontSee('Request refund');
        }
    }

    public function test_legacy_duplicate_booking_renders_without_buttons(): void
    {
        $appointment = $this->pendingBooking();
        $appointment->payment->update([
            'status' => PaymentStatus::Rejected,
            'rejection_category' => 'duplicate_booking',
            'amount_received' => 600,
        ]);
        $appointment->update(['status' => AppointmentStatus::Cancelled]);

        // Client card: prettified legacy label, no actions, no crash.
        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Duplicate booking')
            ->assertDontSee('Continue to book this')
            ->assertDontSee('Send a new proof')
            ->assertDontSee('Request refund');

        // Admin Payments page renders the legacy row without errors.
        $this->actingAs($this->manager)->get(route('admin.payments', ['status' => 'rejected']))
            ->assertOk()
            ->assertSee('Duplicate booking');
    }
}
