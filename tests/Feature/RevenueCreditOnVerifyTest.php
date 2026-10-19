<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\RevenueRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Rule A second-chance arcs (sha, 2026-10-19): a down payment that was
 * rejected earns revenue only when a later payment on the SAME booking is
 * verified. While the booking sits Cancelled the held partial receipt
 * counts ₱0; reviving it (top-up submitted) makes the held money count,
 * and verifying the top-up completes the full down payment, once.
 */
class RevenueCreditOnVerifyTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    private User $manager;

    private User $staff;

    private Service $service;

    private RevenueRecognitionService $revenue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->revenue = app(RevenueRecognitionService::class);

        $this->service = Service::create([
            'name' => 'Revenue Facial (test)', 'category' => 'Facial', 'description' => 'Test',
            'price' => 800, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
    }

    private function pendingBooking(float $paymentAmount = 300): Appointment
    {
        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'booked_price' => 800,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'status' => AppointmentStatus::Unverified,
        ]);
        $appointment->payment()->create([
            'amount' => $paymentAmount,
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

        $this->actingAs($this->manager)->patch(
            route('admin.payments.reject', $appointment->payment->id),
            $payload
        )->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_verify_credits_the_down_payment_immediately(): void
    {
        $appointment = $this->pendingBooking();
        $before = $this->revenue->sumBetween();

        $this->assertEquals(0.0, $this->revenue->amountFor($appointment));

        $this->actingAs($this->manager)->patch(
            route('admin.payments.verify', $appointment->payment->id)
        )->assertRedirect();

        $this->assertSame(AppointmentStatus::Booked, $appointment->refresh()->status);
        $this->assertEquals(300.0, $this->revenue->amountFor($appointment));
        $this->assertEquals(round($before + 300, 2), $this->revenue->sumBetween());
    }

    public function test_rejected_then_topup_then_verify_credits_the_full_down_payment_once(): void
    {
        Storage::fake('public');

        $appointment = $this->pendingBooking();
        $before = $this->revenue->sumBetween();

        // Reject: ₱200 of the ₱300 was actually received.
        $this->reject($appointment, 'insufficient_amount', 200);
        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);

        // Dead booking: the held ₱200 counts ₱0.
        $this->assertEquals(0.0, $this->revenue->amountFor($appointment));
        $this->assertEquals($before, $this->revenue->sumBetween());

        // Client tops up the ₱100 shortfall: booking revives to Unverified,
        // the held ₱200 counts again, the pending top-up does not yet.
        $this->actingAs($this->client)->post(
            route('account.bookings.topup.store', $appointment->id),
            ['method' => 'gcash', 'payment_proof' => UploadedFile::fake()->image('topup.jpg')]
        )->assertRedirect(route('account.bookings'));

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Unverified, $appointment->status);
        $this->assertEquals(200.0, $this->revenue->amountFor($appointment));

        // Manager verifies the top-up: full ₱300 credited, exactly once.
        $topup = $appointment->payments()->where('kind', 'topup')->firstOrFail();
        $this->actingAs($this->manager)->patch(
            route('admin.payments.verify', $topup->id)
        )->assertRedirect();

        $this->assertSame(AppointmentStatus::Booked, $appointment->refresh()->status);
        $this->assertEquals(300.0, $this->revenue->amountFor($appointment));
        $this->assertEquals(round($before + 300, 2), $this->revenue->sumBetween());
    }

    public function test_rejected_then_resubmit_then_verify_credits_the_down_payment(): void
    {
        Storage::fake('public');

        $appointment = $this->pendingBooking();
        $before = $this->revenue->sumBetween();

        $this->reject($appointment, 'invalid_proof');
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);
        $this->assertEquals(0.0, $this->revenue->amountFor($appointment));
        $this->assertEquals($before, $this->revenue->sumBetween());

        $this->actingAs($this->client)->post(
            route('account.bookings.resubmit-proof', $appointment->id),
            ['payment_proof' => UploadedFile::fake()->image('new-proof.jpg')]
        )->assertRedirect(route('account.bookings'));

        $this->assertSame(AppointmentStatus::Unverified, $appointment->refresh()->status);
        $this->assertEquals(0.0, $this->revenue->amountFor($appointment));

        $this->actingAs($this->manager)->patch(
            route('admin.payments.verify', $appointment->payment->id)
        )->assertRedirect();

        $this->assertSame(AppointmentStatus::Booked, $appointment->refresh()->status);
        $this->assertEquals(300.0, $this->revenue->amountFor($appointment));
        $this->assertEquals(round($before + 300, 2), $this->revenue->sumBetween());
    }

    public function test_final_rejection_never_credits_and_cancel_after_verify_removes_the_credit(): void
    {
        $dead = $this->pendingBooking();
        $this->reject($dead, 'no_payment');
        $this->assertEquals(0.0, $this->revenue->amountFor($dead->refresh()));

        $appointment = $this->pendingBooking();
        $before = $this->revenue->sumBetween();

        $this->actingAs($this->manager)->patch(
            route('admin.payments.verify', $appointment->payment->id)
        )->assertRedirect();
        $this->assertEquals(round($before + 300, 2), $this->revenue->sumBetween());

        app(AppointmentService::class)->transition(
            $appointment->fresh(), AppointmentStatus::Cancelled, $this->manager
        );

        $this->assertEquals(0.0, $this->revenue->amountFor($appointment->refresh()));
        $this->assertEquals($before, $this->revenue->sumBetween());
    }
}
