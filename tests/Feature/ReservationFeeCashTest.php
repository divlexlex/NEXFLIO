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
 * Per-service down payment + Cash payment method — end-to-end suite.
 *
 * - Every booked service contributes its own configured down_payment (NULL
 *   means none), capped at its resolved price; extras add theirs too.
 * - Cash is only allowed when nothing is due online. When a down payment
 *   applies, an online method is required and pays exactly that amount.
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

    private Service $pedicure;

    private Service $facial;

    private Service $royalFacial;

    private Service $gentleCleanse;

    private Service $quickTrim;

    private Service $spaPlus;

    private Service $homeMassage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();

        $this->package = Service::create([
            'name' => 'Package LIZ (test)', 'category' => 'Head Spa', 'description' => 'Test',
            'price' => 1899, 'down_payment' => 500, 'duration_minutes' => 90, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
        $this->package->variants()->createMany([
            ['label' => 'Solo', 'price' => 1899, 'guests' => 1, 'sort' => 1],
            ['label' => 'Couple', 'price' => 3699, 'guests' => 2, 'sort' => 2],
            ['label' => 'Barkada', 'price' => 7199, 'guests' => null, 'sort' => 3],
        ]);
        $this->coupleVariantId = $this->package->variants()->where('label', 'Couple')->firstOrFail()->id;

        $this->massage = Service::create([
            'name' => 'Swedish Massage (test)', 'category' => 'Massage', 'description' => 'Test',
            'price' => 599, 'down_payment' => 300, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        $this->cheapHeadSpa = Service::create([
            'name' => 'Cheap Head Spa (test)', 'category' => 'Head Spa', 'description' => 'Test',
            'price' => 200, 'down_payment' => 500, 'duration_minutes' => 30, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        $this->manicure = Service::create([
            'name' => 'Manicure (test)', 'category' => 'Nails', 'description' => 'Test',
            'price' => 179, 'down_payment' => null, 'duration_minutes' => 45, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        $this->pedicure = Service::create([
            'name' => 'Pedicure Spa (test)', 'category' => 'Nails', 'description' => 'Test',
            'price' => 450, 'down_payment' => 225, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        $this->facial = Service::create([
            'name' => 'Signature Facial (test)', 'category' => 'Facial', 'description' => 'Test',
            'price' => 800, 'down_payment' => 300, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        $this->royalFacial = Service::create([
            'name' => 'Royal Treatment (test)', 'category' => 'Facial', 'description' => 'Test',
            'price' => 2500, 'down_payment' => 300, 'duration_minutes' => 90, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        $this->gentleCleanse = Service::create([
            'name' => 'Gentle Cleanse (test)', 'category' => 'Facial', 'description' => 'Test',
            'price' => 300, 'down_payment' => 150, 'duration_minutes' => 30, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        $this->quickTrim = Service::create([
            'name' => 'Quick Trim (test)', 'category' => 'Nails', 'description' => 'Test',
            'price' => 299, 'down_payment' => 149.50, 'duration_minutes' => 30, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        $this->spaPlus = Service::create([
            'name' => 'Spa Pedicure Plus (test)', 'category' => 'Nails', 'description' => 'Test',
            'price' => 500, 'down_payment' => 300, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        $this->homeMassage = Service::create([
            'name' => 'Home Massage (test)', 'category' => 'Massage', 'description' => 'Test',
            'price' => 900, 'down_payment' => 300, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'home',
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

    // (a) Package LIZ Couple online: configured DP 500 due now, 3,199 balance.
    public function test_online_package_booking_charges_only_the_down_payment(): void
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

    // Guests are neither required nor stored anymore: the fee comes from
    // the price rule, and a submitted count is ignored.
    public function test_guests_ignored_and_stored_null_for_variant_less_service(): void
    {
        Storage::fake('public');

        // No guests submitted -> books fine.
        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['service_id' => $this->massage->id])
        )->assertRedirect()->assertSessionHasNoErrors();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertNull($appointment->guests);
        $this->assertEquals(300, (float) $appointment->payment->amount);

        // Submitted guests -> still ignored, fee unchanged.
        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['service_id' => $this->massage->id, 'guests' => 5, 'start_time' => '11:00'])
        )->assertRedirect()->assertSessionHasNoErrors();

        $second = Appointment::latest('id')->firstOrFail();
        $this->assertNull($second->guests);
        $this->assertEquals(300, (float) $second->payment->amount);
    }

    // A configured DP is capped at the service's price (500 -> 200), so the
    // online payment covers the whole booking and nothing is left to settle.
    public function test_down_payment_is_capped_at_the_booked_price(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['service_id' => $this->cheapHeadSpa->id])
        )->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertEquals(200, (float) $appointment->payment->amount);

        $this->actingAs($this->manager)->patch(route('admin.payments.verify', $appointment->payment->id));
        $appointment->refresh();
        $this->assertSame('Paid in full', $appointment->paymentState());

        // Settlement is refused when nothing is owed.
        $this->actingAs($this->manager)->post(
            route('admin.appointments.branch-payment', $appointment->id),
            ['amount' => 10, 'method' => 'cash']
        )->assertSessionHasErrors('branch_payment');
    }

    // (b) Manicure Cash: no proof, created Unverified (a request awaiting
    // branch confirmation), full amount owed at the spa.
    public function test_cash_booking_is_a_request_without_proof(): void
    {
        $response = $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['method' => 'cash', 'payment_proof' => null])
        );

        $response->assertRedirect();
        $appointment = Appointment::latest('id')->firstOrFail();

        $this->assertSame(AppointmentStatus::Unverified, $appointment->status);
        $this->assertEquals(179, (float) $appointment->payment->amount);
        $this->assertSame('cash', $appointment->payment->method);
        $this->assertNull($appointment->payment->proof_path);
        $this->assertSame(PaymentStatus::Pending, $appointment->payment->status);
        $this->assertSame('Awaiting confirmation — full amount payable at the spa', $appointment->paymentState());

        $this->actingAs($this->client)->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Booking Request Submitted')
            ->assertDontSee('Booking Confirmed')
            ->assertSee('Payable at the branch');
    }

    // A manager confirms the Cash request from the Appointments side, and
    // can cancel it there too. The cash row itself stays pending (never
    // "verified" online) in both cases.
    public function test_cash_booking_confirmed_and_cancelled_via_drawer_actions(): void
    {
        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['method' => 'cash', 'payment_proof' => null])
        );
        $appointment = Appointment::latest('id')->firstOrFail();

        // Confirm: Unverified -> Booked through the normal appointment action.
        $this->actingAs($this->manager)->patch(
            route('admin.appointments.status', $appointment->id),
            ['status' => AppointmentStatus::Booked->value]
        )->assertRedirect()->assertSessionHasNoErrors();

        $appointment->refresh()->load('payments');
        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertSame(PaymentStatus::Pending, $appointment->payment->status);
        $this->assertSame('Cash — pay at the branch', $appointment->paymentState());

        // Cancel a fresh Cash request the same way (drawer asks for a reason).
        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['method' => 'cash', 'payment_proof' => null, 'start_time' => '11:00'])
        );
        $cancelled = Appointment::latest('id')->firstOrFail();

        $this->actingAs($this->manager)->patch(
            route('admin.appointments.status', $cancelled->id),
            ['status' => AppointmentStatus::Cancelled->value, 'rejection_reason' => 'Client changed mind by phone.']
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(AppointmentStatus::Cancelled, $cancelled->refresh()->status);
    }

    // An Unverified ONLINE booking still cannot be confirmed from the
    // drawer — only via Payments verification.
    public function test_online_unverified_still_locked_out_of_drawer_confirm(): void
    {
        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['service_id' => $this->massage->id, 'guests' => 2])
        );
        $appointment = Appointment::latest('id')->firstOrFail();

        $this->actingAs($this->manager)->patch(
            route('admin.appointments.status', $appointment->id),
            ['status' => AppointmentStatus::Booked->value]
        )->assertSessionHasErrors('status');

        $this->assertSame(AppointmentStatus::Unverified, $appointment->refresh()->status);
    }

    public function test_wizard_method_list_has_no_card_and_renamed_bank(): void
    {
        $client = $this->actingAs($this->client);
        $tomorrow = now()->addDay()->toDateString();
        $base = ['service' => $this->massage->id, 'date' => $tomorrow, 'time' => '10:00', 'personnel' => 'any', 'guests' => 2];
        $address = ['street_address' => '123 Test St', 'barangay' => 'B1', 'city_municipality' => 'TC', 'province' => 'TP'];

        foreach ([
            $client->get(route('account.booking.branch.payment', $base)),
            $client->get(route('account.booking.home.payment', array_merge($base, ['service' => $this->homeMassage->id], $address))),
        ] as $response) {
            $response->assertOk();
            $html = $response->getContent();
            $this->assertStringNotContainsString('value="card"', $html);
            $this->assertStringContainsString('>Other Bank Transfer<', $html);
            $this->assertStringNotContainsString('>Bank Transfer<', $html);
        }
    }

    public function test_cash_info_box_shows_request_wording(): void
    {
        $client = $this->actingAs($this->client);
        $tomorrow = now()->addDay()->toDateString();
        $base = ['service' => $this->massage->id, 'date' => $tomorrow, 'time' => '10:00', 'personnel' => 'any', 'guests' => 2];
        $address = ['street_address' => '123 Test St', 'barangay' => 'B1', 'city_municipality' => 'TC', 'province' => 'TP'];

        foreach ([
            $client->get(route('account.booking.branch.payment', $base)),
            $client->get(route('account.booking.home.payment', array_merge($base, ['service' => $this->homeMassage->id], $address))),
        ] as $response) {
            $response->assertOk();
            $html = $response->getContent();
            $this->assertStringContainsString('This submits a booking request', $html);
            $this->assertStringNotContainsString('confirmed right away', $html);
        }
    }

    public function test_home_package_cash_booking_is_rejected(): void
    {
        Storage::fake('public');

        $both = Service::create([
            'name' => 'Both Package (test)', 'category' => 'Head Spa', 'description' => 'Test',
            'price' => 1899, 'duration_minutes' => 90, 'status' => 'active', 'service_location_type' => 'both',
        ]);
        $both->variants()->create(['label' => 'Solo', 'price' => 1899, 'guests' => 1, 'sort' => 1]);

        $this->actingAs($this->client)->post(
            route('account.booking.home.store'),
            array_merge(
                $this->branchPayload([
                    'service_id' => $both->id,
                    'variant_id' => $both->variants()->firstOrFail()->id,
                    'method' => 'cash',
                    'payment_proof' => null,
                ]),
                ['street_address' => '123 Test St', 'barangay' => 'B1', 'city_municipality' => 'TC', 'province' => 'TP']
            )
        )->assertSessionHasErrors('method');
    }

    // Packages must pay the reservation fee online — Cash is rejected
    // server-side even when forged past the (option-less) payment step.
    public function test_cash_package_booking_is_rejected(): void
    {
        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload([
                'service_id' => $this->package->id,
                'variant_id' => $this->coupleVariantId,
                'method' => 'cash',
                'payment_proof' => null,
            ])
        )->assertSessionHasErrors('method');

        $this->assertSame(0, Appointment::count());
    }

    public function test_online_booking_still_requires_proof(): void
    {
        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['payment_proof' => null])
        )->assertSessionHasErrors('payment_proof');

        $this->assertSame(0, Appointment::count());
    }

    // (c) Manicure has no configured DP (NULL), so online pays the full 179.
    public function test_no_down_payment_means_online_pays_full_price(): void
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

    // Pedicure 450: fee is half (225.00).
    public function test_450_service_charges_225_fee(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['service_id' => $this->pedicure->id])
        )->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertEquals(225, (float) $appointment->payment->amount);
        $this->assertEquals(450, (float) $appointment->booked_price);
        $this->assertNull($appointment->guests);
    }

    // Price exactly 300: fee is half (150.00).
    public function test_300_service_charges_150_fee(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['service_id' => $this->gentleCleanse->id])
        )->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertEquals(150, (float) $appointment->payment->amount);
        $this->assertNull($appointment->guests);
    }

    // Price 299: fee is half (149.50).
    public function test_299_service_charges_149_50_fee(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['service_id' => $this->quickTrim->id])
        )->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertEquals(149.5, (float) $appointment->payment->amount);
        $this->assertNull($appointment->guests);
    }

    // Price exactly 500 (the boundary): flat 300 fee.
    public function test_500_service_charges_300_fee(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['service_id' => $this->spaPlus->id])
        )->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertEquals(300, (float) $appointment->payment->amount);
        $this->assertNull($appointment->guests);
    }

    // Facial 800: flat 300 fee.
    public function test_facial_charges_flat_300_fee(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['service_id' => $this->facial->id])
        )->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertEquals(300, (float) $appointment->payment->amount);
        $this->assertEquals(800, (float) $appointment->booked_price);
        $this->assertNull($appointment->guests);
    }

    // Price 2500: flat 300 fee (tiers no longer apply without a variant).
    public function test_pricey_service_charges_flat_300_fee(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['service_id' => $this->royalFacial->id])
        )->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertEquals(300, (float) $appointment->payment->amount);
        $this->assertEquals(2500, (float) $appointment->booked_price);
        $this->assertNull($appointment->guests);
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
            ['status' => 'cancelled', 'rejection_reason' => 'Client asked to cancel by phone.']
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

    public function test_home_cash_booking_is_a_request_without_proof(): void
    {
        $this->homeMassage->update(['down_payment' => null]);

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
        $this->assertSame(AppointmentStatus::Unverified, $appointment->status);
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

    // Home online: flat 300 fee due now on the 900 service, not the full price.
    public function test_home_online_booking_charges_flat_300_fee(): void
    {
        Storage::fake('public');

        $address = [
            'street_address' => '123 Test St', 'barangay' => 'Barangay 1',
            'city_municipality' => 'Test City', 'province' => 'Test Province',
        ];

        $this->actingAs($this->client)->post(
            route('account.booking.home.store'),
            array_merge(
                $this->branchPayload(['service_id' => $this->homeMassage->id]),
                $address
            )
        )->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertSame(AppointmentStatus::Unverified, $appointment->status);
        $this->assertEquals(300, (float) $appointment->payment->amount);
        $this->assertEquals(900, (float) $appointment->booked_price);
        $this->assertNull($appointment->guests);
        $this->assertNotNull($appointment->address);
    }

    public function test_home_guests_ignored_for_variant_less_service(): void
    {
        Storage::fake('public');

        $address = [
            'street_address' => '123 Test St', 'barangay' => 'Barangay 1',
            'city_municipality' => 'Test City', 'province' => 'Test Province',
        ];

        $this->actingAs($this->client)->post(
            route('account.booking.home.store'),
            array_merge(
                $this->branchPayload(['service_id' => $this->homeMassage->id, 'guests' => 9]),
                $address
            )
        )->assertRedirect()->assertSessionHasNoErrors();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertNull($appointment->guests);
        $this->assertEquals(300, (float) $appointment->payment->amount);
    }

    // Kiddie Japanese Head Spa keeps the migration's default ₱20 DP.
    public function test_kiddie_uses_default_down_payment(): void
    {
        Storage::fake('public');

        $kiddie = Service::where('name', 'Kiddie Japanese Head Spa')->firstOrFail();

        $payload = $this->branchPayload(['service_id' => $kiddie->id]);
        unset($payload['guests']);

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $payload
        )->assertRedirect()->assertSessionHasNoErrors();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertSame(AppointmentStatus::Unverified, $appointment->status);
        $this->assertEquals(20, (float) $appointment->payment->amount);
        $this->assertEquals(599, (float) $appointment->booked_price);
        $this->assertNull($appointment->guests);
    }

    public function test_kiddie_details_shows_computed_down_payment_note(): void
    {
        $client = $this->actingAs($this->client);
        $tomorrow = now()->addDay()->toDateString();
        $base = ['date' => $tomorrow, 'time' => '10:00', 'personnel' => 'any'];

        $kiddie = Service::where('name', 'Kiddie Japanese Head Spa')->firstOrFail();

        $client->get(route('account.booking.branch.details', array_merge($base, ['service' => $kiddie->id])))
            ->assertOk()
            ->assertDontSee('name="guests"', false)
            ->assertSee('Down payment for this service(s)');

        // A regular Nails service shows the same computed note, no guests field.
        $client->get(route('account.booking.branch.details', array_merge($base, ['service' => $this->pedicure->id])))
            ->assertOk()
            ->assertDontSee('name="guests"', false)
            ->assertSee('Down payment for this service(s)');
    }

    public function test_nails_ignores_guests_and_charges_half_price(): void
    {
        Storage::fake('public');

        // Submitted guests are ignored, not validated.
        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->branchPayload(['service_id' => $this->pedicure->id, 'guests' => 7])
        )->assertRedirect()->assertSessionHasNoErrors();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertEquals(225, (float) $appointment->payment->amount);
        $this->assertNull($appointment->guests);
    }

    public function test_kiddie_cash_is_a_request_when_no_down_payment_is_set(): void
    {
        $kiddie = Service::where('name', 'Kiddie Japanese Head Spa')->firstOrFail();
        $kiddie->update(['down_payment' => null]);

        $payload = $this->branchPayload(['service_id' => $kiddie->id, 'method' => 'cash', 'payment_proof' => null]);
        unset($payload['guests']);

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $payload
        )->assertRedirect();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertSame(AppointmentStatus::Unverified, $appointment->status);
        $this->assertEquals(599, (float) $appointment->payment->amount);
        $this->assertSame('cash', $appointment->payment->method);
        $this->assertNull($appointment->guests);
    }

    public function test_wizard_shows_down_payment_note_and_fee_breakdown(): void
    {
        $client = $this->actingAs($this->client);
        $tomorrow = now()->addDay()->toDateString();
        $base = ['service' => $this->massage->id, 'date' => $tomorrow, 'time' => '10:00', 'personnel' => 'any'];
        $address = ['street_address' => '123 Test St', 'barangay' => 'B1', 'city_municipality' => 'TC', 'province' => 'TP'];

        // Branch details step: no guests field anywhere — the computed down
        // payment note instead (599 -> flat 300 here...).
        $client->get(route('account.booking.branch.details', $base))
            ->assertOk()
            ->assertDontSee('name="guests"', false)
            ->assertSee('Down payment for this service(s)');

        // ...including Nails (450 -> half 225.00).
        $client->get(route('account.booking.branch.details', array_merge($base, ['service' => $this->pedicure->id])))
            ->assertOk()
            ->assertDontSee('name="guests"', false)
            ->assertSee('Down payment for this service(s)');

        // Review + payment: per-service DP, balance, and full breakdown.
        $client->get(route('account.booking.branch.review', $base))
            ->assertOk()
            ->assertSee('Down payment — Swedish Massage (test)')
            ->assertSee('Balance (pay at the branch)')
            ->assertSee('₱300.00');

        // Branch payment step hides Cash when a DP is due, still lists
        // online methods and hides proof via JS.
        $client->get(route('account.booking.branch.payment', $base))
            ->assertOk()
            ->assertDontSee('value="cash"', false)
            ->assertSee('value="maya"', false)
            ->assertDontSee('name="guests"', false)
            ->assertSee('pay-proof-block');

        // Home details step: computed note, no guests selector...
        $client->get(route('account.booking.home.details', array_merge($base, ['service' => $this->homeMassage->id], $address)))
            ->assertOk()
            ->assertDontSee('name="guests"', false)
            ->assertSee('Down payment for this service(s)');

        // Home review: per-service DP and balance.
        $client->get(route('account.booking.home.review', array_merge($base, ['service' => $this->homeMassage->id], $address)))
            ->assertOk()
            ->assertSee('Down payment — Home Massage (test)')
            ->assertSee('Balance (pay to the specialist at your home)')
            ->assertSee('₱300.00');

        // Home payment step: DP breakdown, no cash when DP is due, no guests.
        $client->get(route('account.booking.home.payment', array_merge($base, ['service' => $this->homeMassage->id], $address)))
            ->assertOk()
            ->assertDontSee('value="cash"', false)
            ->assertSee('Down payment — Home Massage (test)')
            ->assertSee('₱300.00')
            ->assertDontSee('name="guests"', false);
    }
}
