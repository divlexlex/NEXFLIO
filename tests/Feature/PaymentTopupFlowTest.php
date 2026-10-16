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
 * Step 2B — top-up ("Continue to book this"). The money recorded at a
 * topup_eligible rejection stays on the appointment as a Verified partial
 * receipt; the client adds only the shortfall on the SAME appointment.
 * Rejected-booking refunds no longer exist, so no refund interplay remains;
 * no new appointment statuses.
 */
class PaymentTopupFlowTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    private User $manager;

    private User $staff;

    private Service $package;

    private int $coupleVariantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::create([
            'name' => 'Topup Staff', 'email' => 'topup-staff@nexflio.test',
            'password' => bcrypt('password'), 'role_id' => User::ROLE_STAFF,
        ]);

        // LIZ-shaped fixture: Couple tier 3699, fee 500.
        $this->package = Service::create([
            'name' => 'Package LIZ (test)', 'category' => 'Head Spa', 'description' => 'Test',
            'price' => 1899, 'duration_minutes' => 90, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
        $this->package->variants()->createMany([
            ['label' => 'Solo', 'price' => 1899, 'guests' => 1, 'sort' => 1],
            ['label' => 'Couple', 'price' => 3699, 'guests' => 2, 'sort' => 2],
            ['label' => 'Barkada', 'price' => 7199, 'guests' => null, 'sort' => 3],
        ]);
        $this->coupleVariantId = $this->package->variants()->where('label', 'Couple')->firstOrFail()->id;
    }

    private function bookCoupleOnline(array $overrides = []): Appointment
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            array_merge([
                'service_id' => $this->package->id,
                'variant_id' => $this->coupleVariantId,
                'personnel_id' => 'any',
                'appointment_date' => now()->addDay()->toDateString(),
                'start_time' => '10:00',
                'method' => 'gcash',
                'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            ], $overrides)
        )->assertRedirect();

        return Appointment::latest('id')->firstOrFail();
    }

    private function rejectPrimary(Appointment $appointment, string $category, $amount = null): void
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

    // (a) Full arc: reject 200 -> partial 200 -> Continue 300 -> topup ->
    // verify -> Booked with the down-payment state off the held 500.
    public function test_full_topup_arc(): void
    {
        $appointment = $this->bookCoupleOnline();
        $this->rejectPrimary($appointment, 'insufficient_amount', 200);
        $appointment->refresh();

        // Partial receipt row: Verified, money + proof carried over.
        $partial = $appointment->payments()->where('kind', 'partial_receipt')->firstOrFail();
        $this->assertEquals(200, (float) $partial->amount);
        $this->assertSame(PaymentStatus::Verified, $partial->status);
        $this->assertSame('gcash', $partial->method);
        $this->assertSame($appointment->payment->proof_path, $partial->proof_path);
        $this->assertSame($this->manager->id, $partial->verified_by);
        $this->assertNotNull($partial->verified_at);
        $this->assertSame($appointment->payment->id, $appointment->refresh()->payment->id);

        // Card: Continue button on the card's bottom line with the
        // ID and personnel, identical solid styling, shortfall 300.
        // No refund button anywhere.
        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Continue to book this — add ₱300.00')
            ->assertDontSee('Request refund')
            ->assertSee('justify-content-between align-items-end', false);

        // Form: fixed amount, no cash, proof check UI.
        $this->actingAs($this->client)->get(route('account.bookings.topup', $appointment->id))
            ->assertOk()
            ->assertSee('Amount to add')
            ->assertSee('₱300.00')
            ->assertDontSee('value="cash"', false)
            ->assertSee('View image');

        // Submit the top-up.
        $this->actingAs($this->client)->post(
            route('account.bookings.topup.store', $appointment->id),
            ['method' => 'gcash', 'payment_proof' => UploadedFile::fake()->image('topup.jpg')]
        )->assertRedirect(route('account.bookings'));

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Unverified, $appointment->status);
        $topup = $appointment->payments()->where('kind', 'topup')->firstOrFail();
        $this->assertEquals(300, (float) $topup->amount);
        $this->assertSame(PaymentStatus::Pending, $topup->status);
        $this->assertSame('gcash', $topup->method);
        $this->assertNotNull($topup->proof_path);

        // Queue badges the top-up row.
        $this->actingAs($this->manager)->get(route('admin.payments', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Top-up');

        // Verify -> Booked, state derived off the held 500.
        $this->actingAs($this->manager)->patch(route('admin.payments.verify', $topup->id))
            ->assertRedirect();
        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertSame(PaymentStatus::Verified, $topup->refresh()->status);
        $this->assertSame('Down payment paid — balance ₱3,199.00', $appointment->paymentState());
    }

    // (b) Slot taken while cancelled: submit refused, Cancelled kept, refund
    // still offered.
    public function test_topup_refused_when_slot_taken(): void
    {
        $date = now()->addDay()->toDateString();
        $appointment = $this->bookCoupleOnline([
            'personnel_id' => $this->staff->id,
            'appointment_date' => $date,
            'start_time' => '11:00',
        ]);
        $this->rejectPrimary($appointment, 'insufficient_amount', 200);

        // Someone else takes the exact slot (walk-in performs no collision
        // check, which is exactly what we need to simulate this).
        $this->actingAs($this->manager)->post(route('admin.appointments.walk-in'), [
            'walk_in_name' => 'Slot Taker',
            'service_id' => $this->package->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => $date,
            'start_time' => '11:00',
        ])->assertRedirect();

        $this->actingAs($this->client)->post(
            route('account.bookings.topup.store', $appointment->id),
            ['method' => 'gcash', 'payment_proof' => UploadedFile::fake()->image('topup.jpg')]
        )->assertSessionHasErrors('topup');

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        $this->assertSame(0, $appointment->payments()->where('kind', 'topup')->count());

        $content = $this->actingAs($this->client)->get(route('account.bookings.topup', $appointment->id))->getContent();
        // Form still renders (slot may free up) but the message explains.
        $this->assertStringContainsString('Amount to add', $content);

        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertDontSee('Request refund of');
    }

    // (c) A submitted top-up hides Continue (active top-up in flight) and
    // no refund is ever offered.
    public function test_submitted_topup_hides_continue_with_no_refund_offered(): void
    {
        $appointment = $this->pendingRejected(200);

        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Continue to book this')
            ->assertDontSee('Request refund');

        $this->actingAs($this->client)->post(
            route('account.bookings.topup.store', $appointment->id),
            ['method' => 'gcash', 'payment_proof' => UploadedFile::fake()->image('topup.jpg')]
        )->assertRedirect();
        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertDontSee('Continue to book this')
            ->assertDontSee('Request refund');

        // ...and the top-up form itself refuses while one is in motion.
        $this->actingAs($this->client)->get(route('account.bookings.topup', $appointment->id))
            ->assertRedirect(route('account.bookings'));
    }

    // (d) Rejecting the topup folds its received amount into the original
    // payment: partials total 300, Continue recomputes to 200.
    public function test_topup_rejection_accumulates_held_money(): void
    {
        $appointment = $this->bookCoupleOnline();
        $this->rejectPrimary($appointment, 'insufficient_amount', 200);

        $this->actingAs($this->client)->post(
            route('account.bookings.topup.store', $appointment->id),
            ['method' => 'gcash', 'payment_proof' => UploadedFile::fake()->image('topup.jpg')]
        )->assertRedirect();
        $topup = $appointment->payments()->where('kind', 'topup')->firstOrFail();

        $this->actingAs($this->manager)->patch(
            route('admin.payments.reject', $topup->id),
            ['rejection_category' => 'insufficient_amount', 'amount_received' => 100]
        )->assertRedirect()->assertSessionHasNoErrors();

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        $this->assertSame(PaymentStatus::Rejected, $topup->refresh()->status);
        $this->assertEquals(300, $appointment->partialReceiptTotal());
        $this->assertEquals(200, $appointment->shortfall());
        $this->assertEquals(300, (float) $appointment->payment->amount_received);

        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Continue to book this — add ₱200.00')
            ->assertDontSee('Request refund');
    }

    // (e) Ineligible rejections never create partials or Continue; past
    // slots never offer Continue.
    public function test_ineligible_and_past_bookings_offer_no_continue(): void
    {
        $appointment = $this->bookCoupleOnline();
        $this->rejectPrimary($appointment, 'invalid_proof');

        $appointment->refresh();
        $this->assertSame(0, $appointment->payments()->where('kind', 'partial_receipt')->count());
        $this->assertFalse($appointment->canContinueBooking());
        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertDontSee('Continue to book this')
            ->assertSee('Send a new proof');

        $past = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->package->id,
            'variant_label' => 'Couple',
            'booked_price' => 3699,
            'personnel_id' => null,
            'appointment_date' => now()->subDay()->toDateString(),
            'start_time' => '10:00',
            'status' => AppointmentStatus::Cancelled,
        ]);
        $past->payment()->create([
            'amount' => 500,
            'method' => 'gcash',
            'proof_path' => 'payment_proofs/old.jpg',
            'status' => PaymentStatus::Rejected,
            'rejection_category' => 'insufficient_amount',
            'amount_received' => 200,
        ]);

        $this->assertFalse($past->refresh()->canContinueBooking());
        $this->actingAs($this->client)->get(route('account.bookings'))
            ->assertOk()
            ->assertDontSee('Continue to book this — add');
    }

    private function pendingRejected(int $received): Appointment
    {
        $appointment = $this->bookCoupleOnline();
        $this->rejectPrimary($appointment, 'insufficient_amount', $received);

        return $appointment->refresh();
    }
}
