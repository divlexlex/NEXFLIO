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
 * Reservation fee (down payment) + Cash payment method — end-to-end suite.
 *
 * - Fee fixtures mirror the real catalog: a Head Spa package with
 *   Solo 1899 / Couple 3699 / Barkada 7199 tiers (like Package LIZ), a
 *   variant-less Massage service (guest-count fee), a cheap Head Spa
 *   service (fee capped at price), and a non-fee Nails service (Manicure).
 * - Runs against the isolated sqlite :memory: test database (phpunit.xml),
 *   never the real MySQL database.
 */
class ReservationFeeCashTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    private User $manager;

    private Service $package;

    private int $coupleVariantId;

    private Service $massage;

    private Service $cheapHeadSpa;

    private Service $manicure;

    private Service $homeMassage;

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
            ['label' => 'Barkada', 'price' => 7199, 'guests' => null, 'sort' => 3],
        ]);
        $this->coupleVariantId = $this->package->variants()->where('label', 'Couple')->firstOrFail()->id;

        $this->massage = Service::create([
            'name' => 'Swedish Massage (test)', 'category' => 'Massage', 'description' => 'Test',
            'price' => 599, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        $this->cheapHeadSpa = Service::create([
            'name' => 'Cheap Head Spa (test)', 'category' => 'Head Spa', 'description' => 'Test',
            'price' => 200, 'duration_minutes' => 30, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        $this->manicure = Service::create([
            'name' => 'Manicure (test)', 'category' => 'Nails', 'description' => 'Test',
            'price' => 179, 'duration_minutes' => 45, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        $this->homeMassage = Service::create([
            'name' => 'Home Massage (test)', 'category' => 'Massage', 'description' => 'Test',
            'price' => 900, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'home',
        ]);
    }

    private function branchPayload(array $overrides = []): array
    {
        return array_merge([
            'service_id' => $this->manicure->id,
            'personnel_id' => 'any',
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ], $overrides);
    }

    // (a) Package LIZ Couple online: fee 500 due now, 3,199 balance.
    public function test_online_package_booking_charges_only_the_fee(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload([
                'service_id' => $this->package->id,
                'variant_id' => $this->coupleVariantId,
            ])
        );

        $response->assertRedirect();
        $appointment = Appointment::latest('id')->firstOrFail();

        $this->assertSame(AppointmentStatus::Unverified, $appointment->status);
        $this->assertEquals(3699, (float) $appointment->booked_price);
        $this->assertNull($appointment->guests);
        $this->assertEquals(500, (float) $appointment->payment->amount);
        $this->assertSame('gcash', $appointment->payment->method);
        $this->assertSame(PaymentStatus::Pending, $appointment->payment->status);
        $this->assertNotNull($appointment->payment->proof_path);
        $this->assertSame('Awaiting payment verification', $appointment->paymentState());
    }

    // (a) continued: verify -> down-payment state -> branch settlement -> paid in full.
    public function test_fee_flow_verify_then_settle_reaches_paid_in_full(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload([
                'service_id' => $this->package->id,
                'variant_id' => $this->coupleVariantId,
            ])
        );
        $appointment = Appointment::latest('id')->firstOrFail();

        $this->actingAs($this->manager)->patch(route('admin.payments.verify', $appointment->payment->id))
            ->assertRedirect();
        $appointment->refresh()->load('payments');

        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertSame('Down payment paid — balance ₱3,199.00', $appointment->paymentState());

        $this->actingAs($this->manager)->post(
            route('admin.appointments.branch-payment', $appointment->id),
            ['amount' => 3199, 'method' => 'cash']
        )->assertRedirect()->assertSessionHas('success');
        $appointment->refresh()->load('payments');

        $settlement = $appointment->payments()->reorder()->latest('id')->firstOrFail();
        $this->assertEquals(3199, (float) $settlement->amount);
        $this->assertSame(PaymentStatus::Verified, $settlement->status);
        $this->assertSame($this->manager->id, $settlement->verified_by);
        $this->assertNotNull($settlement->verified_at);
        $this->assertSame('Paid in full', $appointment->paymentState());
        // Settlement never moves the appointment status itself.
        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
    }

    public function test_guests_required_for_non_variant_fee_service(): void
    {
        Storage::fake('public');

        // Missing guests -> rejected.
        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['service_id' => $this->massage->id])
        )->assertSessionHasErrors('guests');

        // 2 guests -> Couple tier (500), saved on the appointment.
        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['service_id' => $this->massage->id, 'guests' => 2])
        )->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertSame(2, (int) $appointment->guests);
        $this->assertEquals(500, (float) $appointment->payment->amount);
        $this->assertEquals(599, (float) $appointment->booked_price);
    }

    public function test_fee_capped_at_booking_price(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['service_id' => $this->cheapHeadSpa->id, 'guests' => 1])
        )->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        // Single tier (300) capped at the 200 price — no balance left.
        $this->assertEquals(200, (float) $appointment->payment->amount);

        $this->actingAs($this->manager)->patch(route('admin.payments.verify', $appointment->payment->id));
        $this->assertSame('Paid in full', $appointment->refresh()->paymentState());

        // And settlement is refused when nothing is owed.
        $this->actingAs($this->manager)->post(
            route('admin.appointments.branch-payment', $appointment->id),
            ['amount' => 10, 'method' => 'cash']
        )->assertSessionHasErrors('branch_payment');
    }

    // (b) Manicure Cash: no proof, Booked at once, pay-at-branch state.
    public function test_cash_booking_confirmed_at_once_without_proof(): void
    {
        $response = $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['method' => 'cash', 'payment_proof' => null])
        );

        $response->assertRedirect();
        $appointment = Appointment::latest('id')->firstOrFail();

        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertEquals(179, (float) $appointment->payment->amount);
        $this->assertSame('cash', $appointment->payment->method);
        $this->assertNull($appointment->payment->proof_path);
        $this->assertSame(PaymentStatus::Pending, $appointment->payment->status);
        $this->assertSame('Cash — pay at the branch', $appointment->paymentState());

        $this->actingAs($this->client)->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Booking Confirmed')
            ->assertSee('Payable at the branch');
    }

    public function test_cash_fee_booking_owes_full_price_at_branch(): void
    {
        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload([
                'service_id' => $this->package->id,
                'variant_id' => $this->coupleVariantId,
                'method' => 'cash',
                'payment_proof' => null,
            ])
        )->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertEquals(3699, (float) $appointment->payment->amount);
        $this->assertSame('Cash — pay at the branch', $appointment->paymentState());
    }

    public function test_online_booking_still_requires_proof(): void
    {
        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['payment_proof' => null])
        )->assertSessionHasErrors('payment_proof');

        $this->assertSame(0, Appointment::count());
    }

    // (c) Manicure GCash: unchanged behaviour — full price, verify -> paid in full.
    public function test_noncash_nonfee_booking_behaviour_unchanged(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload()
        )->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertSame(AppointmentStatus::Unverified, $appointment->status);
        $this->assertEquals(179, (float) $appointment->payment->amount);
        $this->assertNull($appointment->guests);

        $this->actingAs($this->manager)->patch(route('admin.payments.verify', $appointment->payment->id));
        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertSame('Paid in full', $appointment->paymentState());
    }

    public function test_payment_method_must_be_known(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['method' => 'crypto'])
        )->assertSessionHasErrors('method');
    }

    public function test_new_online_methods_accepted(): void
    {
        Storage::fake('public');

        foreach (['maya', 'gotyme'] as $method) {
            $this->actingAs($this->client)->post(
                route('account.booking.branch.store'),
                $this->branchPayload(['method' => $method])
            )->assertRedirect();
            $this->assertSame($method, Appointment::latest('id')->firstOrFail()->payment->method);
        }
    }

    public function test_settlement_refused_for_cancelled_appointment(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['method' => 'cash', 'payment_proof' => null])
        );
        $appointment = Appointment::latest('id')->firstOrFail();

        $this->actingAs($this->manager)->patch(
            route('admin.appointments.status', $appointment->id),
            ['status' => 'cancelled']
        )->assertRedirect();
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->refresh()->status);

        $this->actingAs($this->manager)->post(
            route('admin.appointments.branch-payment', $appointment->id),
            ['amount' => 179, 'method' => 'cash']
        )->assertSessionHasErrors('branch_payment');

        $this->assertSame(1, $appointment->payments()->count());
    }

    public function test_cancel_never_alters_verified_payments(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload()
        );
        $appointment = Appointment::latest('id')->firstOrFail();

        $this->actingAs($this->manager)->patch(route('admin.payments.verify', $appointment->payment->id));
        $this->assertSame(PaymentStatus::Verified, $appointment->refresh()->payment->status);

        $this->actingAs($this->manager)->patch(
            route('admin.appointments.status', $appointment->id),
            ['status' => 'cancelled']
        )->assertRedirect();

        // The verified payment row survives cancellation untouched.
        $this->assertSame(PaymentStatus::Verified, $appointment->refresh()->payment->status);
    }

    public function test_home_cash_booking_confirmed_without_proof(): void
    {
        $address = [
            'street_address' => '123 Test St', 'barangay' => 'Barangay 1',
            'city_municipality' => 'Test City', 'province' => 'Test Province',
        ];

        $this->actingAs($this->client)->post(
            route('account.booking.home.store'),
            array_merge(
                $this->branchPayload(['service_id' => $this->homeMassage->id, 'method' => 'cash', 'payment_proof' => null]),
                $address
            )
        )->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertSame('cash', $appointment->payment->method);
        $this->assertNotNull($appointment->address);
    }

    public function test_home_online_booking_still_requires_proof(): void
    {
        $address = [
            'street_address' => '123 Test St', 'barangay' => 'Barangay 1',
            'city_municipality' => 'Test City', 'province' => 'Test Province',
        ];

        $this->actingAs($this->client)->post(
            route('account.booking.home.store'),
            array_merge(
                $this->branchPayload(['service_id' => $this->homeMassage->id, 'payment_proof' => null]),
                $address
            )
        )->assertSessionHasErrors('payment_proof');
    }

    public function test_wizard_shows_guests_and_fee_breakdown(): void
    {
        $client = $this->actingAs($this->client);
        $tomorrow = now()->addDay()->toDateString();
        $base = ['service' => $this->massage->id, 'date' => $tomorrow, 'time' => '10:00', 'personnel' => 'any'];

        // Details step: required guests selector for the variant-less fee service...
        $client->get(route('account.booking.branch.details', $base))
            ->assertOk()
            ->assertSee('name="guests"', false);

        // ...but not for a non-fee service.
        $client->get(route('account.booking.branch.details', array_merge($base, ['service' => $this->manicure->id])))
            ->assertOk()
            ->assertDontSee('name="guests"', false);

        // Review + payment: Price / fee / balance breakdown carried with guests.
        $withGuests = array_merge($base, ['guests' => 2]);
        $client->get(route('account.booking.branch.review', $withGuests))
            ->assertOk()
            ->assertSee('Reservation fee (due now)')
            ->assertSee('Balance (pay at the branch)')
            ->assertSee('₱500.00');

        // Branch payment step lists every method incl. Cash and hides proof via JS.
        $client->get(route('account.booking.branch.payment', $withGuests))
            ->assertOk()
            ->assertSee('value="cash"', false)
            ->assertSee('value="maya"', false)
            ->assertSee('name="guests"', false)
            ->assertSee('pay-proof-block');

        // Home payment step: methods + hide-proof only, no fee breakdown.
        $client->get(route('account.booking.home.payment', array_merge(
            ['service' => $this->homeMassage->id, 'date' => $tomorrow, 'time' => '10:00', 'personnel' => 'any'],
            ['street_address' => '123 Test St', 'barangay' => 'B1', 'city_municipality' => 'TC', 'province' => 'TP']
        )))->assertOk()->assertSee('value="cash"', false);
    }
}
