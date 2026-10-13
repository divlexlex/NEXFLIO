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
 * Payments page renders ONE card per appointment: header badge, details,
 * money summary (online / branch / balance), compact payment rows, and the
 * tab-appropriate actions.
 */
class PaymentsAppointmentCardsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    private User $manager;

    private Service $package;

    private int $coupleVariantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();

        $this->package = Service::create([
            'name' => 'Package LIZ (test)', 'category' => 'Head Spa', 'description' => 'Test',
            'price' => 1899, 'duration_minutes' => 90, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
        $this->package->variants()->createMany([
            ['label' => 'Solo', 'price' => 1899, 'guests' => 1, 'sort' => 1],
            ['label' => 'Couple', 'price' => 3699, 'guests' => 2, 'sort' => 2],
        ]);
        $this->coupleVariantId = $this->package->variants()->where('label', 'Couple')->firstOrFail()->id;
    }

    // (a) Verified tab: ONE card for partial 200 + topup 300 — online 500,
    // balance 3199, ONE settle form, badged compact rows.
    public function test_verified_tab_shows_one_card_per_appointment(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(route('account.booking.branch.store'), [
            'service_id' => $this->package->id,
            'variant_id' => $this->coupleVariantId,
            'personnel_id' => 'any',
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertRedirect();
        $appointment = Appointment::latest('id')->firstOrFail();

        $this->actingAs($this->manager)->patch(
            route('admin.payments.reject', $appointment->payment->id),
            ['rejection_category' => 'insufficient_amount', 'amount_received' => 200]
        )->assertRedirect();

        $this->actingAs($this->client)->post(
            route('account.bookings.topup.store', $appointment->id),
            ['method' => 'gcash', 'payment_proof' => UploadedFile::fake()->image('topup.jpg')]
        )->assertRedirect();
        $topup = $appointment->payments()->where('kind', 'topup')->firstOrFail();

        $this->actingAs($this->manager)->patch(route('admin.payments.verify', $topup->id))
            ->assertRedirect();
        $this->assertSame(AppointmentStatus::Booked, $appointment->refresh()->status);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'verified']))
            ->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Package LIZ (test)'));
        $this->assertStringContainsString('Paid online ₱500.00', $html);
        $this->assertStringContainsString('Paid at branch ₱0.00', $html);
        $this->assertStringContainsString('Balance due ₱3,199.00', $html);
        $this->assertSame(1, substr_count($html, 'Record branch payment'));
        $this->assertStringContainsString('Partial receipt', $html);
        $this->assertStringContainsString('Top-up', $html);
        $this->assertStringContainsString('₱200.00', $html);
        $this->assertStringContainsString('₱300.00', $html);
    }

    // (b) Recording the balance flips the card: Paid in full badge, branch
    // line, zero balance, no form.
    public function test_settled_card_shows_paid_in_full(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(route('account.booking.branch.store'), [
            'service_id' => $this->package->id,
            'variant_id' => $this->coupleVariantId,
            'personnel_id' => 'any',
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertRedirect();
        $appointment = Appointment::latest('id')->firstOrFail();

        $this->actingAs($this->manager)->patch(
            route('admin.payments.verify', $appointment->payment->id)
        )->assertRedirect();

        $this->actingAs($this->manager)->post(
            route('admin.appointments.branch-payment', $appointment->id),
            ['amount' => 3199, 'method' => 'cash']
        )->assertRedirect();

        $this->assertSame('Paid in full', $appointment->refresh()->paymentState());

        $html = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'verified']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Paid in full', $html);
        $this->assertStringContainsString('Paid at branch ₱3,199.00', $html);
        $this->assertStringContainsString('Balance due ₱0.00', $html);
        $this->assertStringNotContainsString('Record branch payment', $html);
    }

    // (c) Pending tab: large proof + Verify/Reject, no settle form.
    public function test_pending_card_shows_large_proof_and_no_settle(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(route('account.booking.branch.store'), [
            'service_id' => $this->package->id,
            'variant_id' => $this->coupleVariantId,
            'personnel_id' => 'any',
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertRedirect();

        $html = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'pending']))
            ->assertOk()->getContent();

        $appointment = Appointment::latest('id')->firstOrFail();

        $this->assertStringContainsString('max-height: 180px', $html);
        $this->assertStringContainsString("/admin/payments/{$appointment->payment->id}/verify", $html);
        $this->assertStringContainsString('data-bs-target="#rejectModal"', $html);
        $this->assertStringContainsString('Pending verification', $html);
        $this->assertStringNotContainsString('Record branch payment', $html);
    }

    // (d) Rejected tab: category + refund status on the card; mark-sent works.
    public function test_rejected_card_shows_category_and_refund_action(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(route('account.booking.branch.store'), [
            'service_id' => $this->package->id,
            'variant_id' => $this->coupleVariantId,
            'personnel_id' => 'any',
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertRedirect();
        $appointment = Appointment::latest('id')->firstOrFail();

        $this->actingAs($this->manager)->patch(
            route('admin.payments.reject', $appointment->payment->id),
            ['rejection_category' => 'insufficient_amount', 'amount_received' => 200]
        )->assertRedirect();
        $this->actingAs($this->client)->post(route('account.bookings.refund', $appointment->id))
            ->assertRedirect();

        $html = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'rejected']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Cancelled', $html);
        $this->assertStringContainsString('Insufficient amount sent', $html);
        $this->assertStringContainsString('Refund requested: ₱200.00', $html);

        $this->actingAs($this->manager)
            ->patch(route('admin.payments.refund-sent', $appointment->payment->id))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('sent', $appointment->payment->refresh()->refund_status);
    }
}
