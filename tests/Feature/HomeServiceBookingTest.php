<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\AppointmentAddress;
use App\Models\ClientAddress;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 3B regression suite — covers both the authenticated-Client booking
 * modal fix and the new Home Service Booking flow end-to-end, per the
 * Phase 3B completion report's required regression tests 1-10. Runs against
 * the isolated sqlite :memory: test database (see phpunit.xml), never the
 * real nexflio MySQL database.
 */
class HomeServiceBookingTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    private User $clientB;

    private Service $branchService;

    private Service $homeService;

    private Service $bothService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();

        $this->clientB = User::create([
            'name' => 'Second Client',
            'email' => 'client-b@nexflio.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_CLIENT,
        ]);

        $this->branchService = Service::create([
            'name' => 'Branch Only Massage', 'category' => 'Massage', 'description' => 'Test',
            'price' => 600, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
        $this->homeService = Service::create([
            'name' => 'Home Only Massage', 'category' => 'Massage', 'description' => 'Test',
            'price' => 900, 'down_payment' => 300, 'duration_minutes' => 90, 'status' => 'active', 'service_location_type' => 'home',
        ]);
        $this->bothService = Service::create([
            'name' => 'Either Massage', 'category' => 'Massage', 'description' => 'Test',
            'price' => 700, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'both',
        ]);
    }

    private function makeHomeExtra(
        string $name = 'Extra Home Massage',
        float $price = 400,
        int $duration = 30,
        ?float $downPayment = null,
        string $category = 'Massage',
        string $location = 'home'
    ): Service {
        return Service::create([
            'name' => $name,
            'category' => $category,
            'description' => 'Test extra',
            'price' => $price,
            'down_payment' => $downPayment,
            'duration_minutes' => $duration,
            'status' => 'active',
            'service_location_type' => $location,
        ]);
    }

    /** @return array<string, mixed> */
    private function homePayload(array $overrides = []): array
    {
        return array_merge([
            'service_id' => $this->homeService->id,
            'personnel_id' => 'any',
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '13:00',
            'method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            'street_address' => '1 Test St.',
            'barangay' => 'Barangay 1',
            'city_municipality' => 'Test City',
            'province' => 'Test Province',
        ], $overrides);
    }

    // 1. Guest + a real service's detail page -> Book -> Guest modal.
    // The whole catalog is real, DB-backed Service rows — this is checked on
    // the detail page, which is what a Guest actually reaches from a
    // Services grid card.
    public function test_guest_sees_guest_modal_on_a_service_detail_page(): void
    {
        $this->get("/services/{$this->branchService->id}")
            ->assertOk()
            ->assertSee('data-bs-target="#getAppModal"', false);
    }

    // Modal regression fix: authenticated Client must NOT get the Guest
    // modal — routed to the real booking start chooser instead.
    public function test_authenticated_client_does_not_see_guest_modal_on_services_page(): void
    {
        $response = $this->actingAs($this->client)->get('/services');

        $response->assertOk();
        $response->assertDontSee('data-bs-target="#getAppModal"', false);
        $response->assertSee(route('account.booking.start'), false);
    }

    // 2. Logged-in Client + branch service -> Book -> no modal, Branch flow.
    public function test_authenticated_client_branch_service_detail_links_to_branch_booking(): void
    {
        $response = $this->actingAs($this->client)->get(route('catalog.service', $this->branchService->id));

        $response->assertOk();
        $response->assertDontSee('data-bs-target="#getAppModal"', false);
        $response->assertSee(route('account.booking.branch.schedule', ['service' => $this->branchService->id]), false);
    }

    // 3. Logged-in Client + home service -> Book -> no modal, Home Service flow.
    public function test_authenticated_client_home_service_detail_links_to_home_booking(): void
    {
        $response = $this->actingAs($this->client)->get(route('catalog.service', $this->homeService->id));

        $response->assertOk();
        $response->assertDontSee('data-bs-target="#getAppModal"', false);
        $response->assertSee(route('account.booking.home.address', ['service' => $this->homeService->id]), false);
    }

    // 4. Logged-in Client + both service -> Book -> no modal, location chooser.
    public function test_authenticated_client_both_service_detail_links_to_location_chooser(): void
    {
        $response = $this->actingAs($this->client)->get(route('catalog.service', $this->bothService->id));

        $response->assertOk();
        $response->assertDontSee('data-bs-target="#getAppModal"', false);
        $response->assertSee(route('account.booking.location', ['service' => $this->bothService->id]), false);

        $chooser = $this->actingAs($this->client)->get(route('account.booking.location', ['service' => $this->bothService->id]));
        $chooser->assertOk()->assertSee('Visit Branch')->assertSee('Home Service');
    }

    // Server-side enforcement: a branch-only service can't be reached
    // through the Home Service wizard even by a crafted query string.
    public function test_home_address_step_rejects_a_branch_only_service(): void
    {
        $this->actingAs($this->client)
            ->get(route('account.booking.home.address', ['service' => $this->branchService->id]))
            ->assertRedirect(route('account.booking.home.service'));
    }

    public function test_branch_schedule_step_rejects_a_home_only_service(): void
    {
        $this->actingAs($this->client)
            ->get(route('account.booking.branch.schedule', ['service' => $this->homeService->id]))
            ->assertRedirect(route('account.booking.branch.service'));
    }

    // 5. General "Book Appointment" CTA for an authenticated Client -> no
    // modal, booking start chooser offering BOTH real flows (Branch and Home
    // Service) with no "Coming Soon" stub.
    public function test_booking_start_page_offers_branch_and_home_booking(): void
    {
        $response = $this->actingAs($this->client)->get(route('account.booking.start'));

        $response->assertOk();
        $response->assertSee(route('account.booking.branch.service'), false);
        $response->assertSee(route('account.booking.home.service'), false);
        $response->assertDontSee('Coming Soon');
    }

    // The site-wide "Book an Appointment" modal must offer the real Home
    // Service grid (home-bookable records only), not the old app stub.
    public function test_service_picker_modal_offers_home_services(): void
    {
        $response = $this->actingAs($this->client)->get(route('account.booking.start'));

        $response->assertOk();
        $response->assertSee(route('account.booking.home.service.show', $this->homeService->id), false);
        $response->assertDontSee('book a Home Service visit through the Perfect Nails app');
    }

    public function test_home_service_detail_has_service_card_without_extras_picker(): void
    {
        $this->makeHomeExtra('Extra Visual Massage', 400, 30, 50);

        $response = $this->actingAs($this->client)
            ->get(route('account.booking.home.service.show', $this->homeService->id));

        $response->assertOk();
        $response->assertSee('nx-booking-floating-section', false);
        $response->assertSee('Home Service');
        $response->assertSee('Service Details');
        $response->assertSee('Step 1 of 6');
        $response->assertSee('Down payment: ₱300.00');
        // Extras moved to the Schedule step: the service-detail step must not
        // offer the old inline AVAIL SERVICES picker anymore.
        $response->assertDontSee('Avail Services');
        $response->assertDontSee('Add more services to this same visit');
        $response->assertDontSee('data-extra-service', false);
        $response->assertDontSee('data-extra-variant', false);
        $response->assertDontSee('data-extra-personnel', false);
        $response->assertDontSee('data-extra-remove', false);
        $response->assertSee(route('account.booking.home.address'), false);
        $response->assertSee('name="service" value="'.$this->homeService->id.'"', false);
    }

    public function test_home_wizard_steps_render_with_extras(): void
    {
        $extra = $this->makeHomeExtra('Extra Wizard Massage', 400, 30, null);
        $staff = User::where('email', 'staff@nexflio.test')->firstOrFail();

        $extras = [
            ['service_id' => $extra->id, 'personnel_id' => $staff->id, 'start_time' => '14:00'],
        ];
        $address = [
            'street_address' => '1 Test St.',
            'barangay' => 'Barangay 1',
            'city_municipality' => 'Test City',
            'province' => 'Test Province',
            'postal_code' => '1000',
        ];
        $base = ['service' => $this->homeService->id, 'extras' => $extras];
        $date = now()->addDay()->toDateString();

        $this->actingAs($this->client)
            ->get(route('account.booking.home.service.show', ['id' => $this->homeService->id, 'extras' => $extras]))
            ->assertOk();

        $this->get(route('account.booking.home.address', $base))->assertOk();

        $schedule = array_merge($base, $address, [
            'date' => $date,
            'personnel' => 'any',
        ]);
        $this->get(route('account.booking.home.schedule', $schedule))->assertOk();

        $selection = array_merge($schedule, ['time' => '13:00']);
        $this->get(route('account.booking.home.details', $selection))->assertOk();
        $this->get(route('account.booking.home.review', $selection))->assertOk();
        $this->get(route('account.booking.home.payment', $selection))->assertOk();
    }

    // 6. A non-existent service id can never produce a fake booking: hitting
    // the wizard with a bogus id must gracefully redirect back to real,
    // eligible services.
    public function test_nonexistent_service_id_can_never_reach_a_booking_route(): void
    {
        // There's no Service row with this id; hitting the wizard with a
        // bogus id must bounce back to real service selection, never
        // render/accept it.
        $this->actingAs($this->client)
            ->get(route('account.booking.branch.schedule', ['service' => 999999]))
            ->assertRedirect(route('account.booking.branch.service'));

        $this->actingAs($this->client)
            ->get(route('account.booking.home.address', ['service' => 999999]))
            ->assertRedirect(route('account.booking.home.service'));
    }

    // 9 + 10. Full Home Service submission: real DB writes (appointment,
    // payment, appointment_addresses), then the required historical address
    // integrity test.
    public function test_home_service_booking_creates_real_records_and_preserves_address_history(): void
    {
        Storage::fake('public');

        $addressA = ClientAddress::create([
            'user_id' => $this->client->id,
            'street_address' => '123 Address A St.',
            'barangay' => 'Barangay A',
            'city_municipality' => 'Caloocan',
            'province' => 'Metro Manila',
            'postal_code' => '1400',
            'is_default' => true,
        ]);

        $date = now()->addDay()->toDateString();

        $payload = [
            'service_id' => $this->homeService->id,
            'personnel_id' => 'any',
            'appointment_date' => $date,
            'start_time' => '13:00',
            'notes' => 'Gate code 1234.',
            'method' => 'gcash',
            'guests' => 1,
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            'source_client_address_id' => $addressA->id,
            'street_address' => $addressA->street_address,
            'barangay' => $addressA->barangay,
            'city_municipality' => $addressA->city_municipality,
            'province' => $addressA->province,
            'postal_code' => $addressA->postal_code,
        ];

        $response = $this->actingAs($this->client)->post(route('account.booking.home.store'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $appointment = Appointment::where('user_id', $this->client->id)
            ->where('service_id', $this->homeService->id)
            ->firstOrFail();

        $this->assertSame(AppointmentStatus::Unverified, $appointment->status);
        $this->assertSame('300.00', (string) $appointment->payment->amount);
        $this->assertSame(PaymentStatus::Pending, $appointment->payment->status);
        $this->assertNotNull($appointment->payment->proof_path);

        $snapshot = $appointment->address;
        $this->assertInstanceOf(AppointmentAddress::class, $snapshot);
        $this->assertSame('123 Address A St.', $snapshot->street_address);
        $this->assertSame($addressA->id, $snapshot->source_client_address_id);

        // Historical Address Test: change the Client's saved default
        // address to Address B, then confirm the existing appointment's
        // snapshot still shows Address A.
        $addressA->update([
            'street_address' => '456 Address B Ave.',
            'barangay' => 'Barangay B',
            'city_municipality' => 'Malabon',
            'province' => 'Metro Manila',
            'postal_code' => '1470',
        ]);

        $snapshot->refresh();
        $this->assertSame('123 Address A St.', $snapshot->street_address);
        $this->assertSame('Barangay A', $snapshot->barangay);

        // 8. Client A's appointment (and its address) is not visible to
        // Client B via the shared success/my-bookings ownership scoping.
        $this->actingAs($this->clientB)
            ->get(route('account.booking.home.success', $appointment->id))
            ->assertNotFound();

        $bClientBookings = $this->actingAs($this->clientB)->get(route('account.bookings'));
        $bClientBookings->assertOk()->assertDontSee($snapshot->street_address);

        $aClientBookings = $this->actingAs($this->client)->get(route('account.bookings'));
        $aClientBookings->assertOk()->assertSee('123 Address A St.');
    }

    // Server-side enforcement: a Home Service submission for a branch-only
    // service must be rejected even if the form is posted directly.
    public function test_home_store_rejects_a_branch_only_service(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->client)->post(route('account.booking.home.store'), [
            'service_id' => $this->branchService->id,
            'personnel_id' => 'any',
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '13:00',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            'street_address' => '1 Test St.', 'barangay' => 'B', 'city_municipality' => 'C', 'province' => 'P',
        ]);

        $response->assertSessionHasErrors('service_id');
        $this->assertDatabaseCount('appointments', 0);
    }

    // Extras parity: main + one extra becomes ONE appointment, ONE reference
    // code and a source='booking' added-services row; the single payment is
    // the sum of both configured down payments.
    public function test_home_service_booking_with_extras_creates_one_appointment_with_added_services(): void
    {
        Storage::fake('public');

        $this->homeService->update(['down_payment' => 20]);
        $extra = $this->makeHomeExtra('Extra Home Massage', 400, 30, 20);
        $staff = User::where('email', 'staff@nexflio.test')->firstOrFail();

        $response = $this->actingAs($this->client)->post(route('account.booking.home.store'), $this->homePayload([
            'extras' => [
                ['service_id' => $extra->id, 'personnel_id' => $staff->id, 'start_time' => '14:00'],
            ],
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertSame(1, Appointment::count());
        $appointment = Appointment::with(['addedServices', 'payment', 'address'])->firstOrFail();

        $this->assertEquals(900 + 400, (float) $appointment->booked_price);
        $this->assertNotNull($appointment->reference_code);

        $row = $appointment->addedServices->firstOrFail();
        $this->assertSame($extra->id, $row->service_id);
        $this->assertSame('booking', $row->source);
        $this->assertEquals(400, (float) $row->price);
        $this->assertSame($staff->id, $row->personnel_id);
        $this->assertSame('14:00', $row->start_time->format('H:i'));

        $this->assertSame(1, $appointment->payments()->count());
        $this->assertEquals(40, (float) $appointment->payment->amount);
        $this->assertSame(PaymentStatus::Pending, $appointment->payment->status);

        $this->assertInstanceOf(AppointmentAddress::class, $appointment->address);
        $this->assertSame('1 Test St.', $appointment->address->street_address);
    }

    // Cash is refused when an EXTRA (not the main service) raises a down
    // payment, and a refusal writes nothing.
    public function test_home_store_rejects_cash_when_extras_raise_a_down_payment(): void
    {
        Storage::fake('public');

        $this->homeService->update(['down_payment' => null]);
        $extra = $this->makeHomeExtra('Extra Down Payment', 400, 30, 20);

        $response = $this->actingAs($this->client)->post(route('account.booking.home.store'), $this->homePayload([
            'method' => 'cash',
            'payment_proof' => null,
            'extras' => [['service_id' => $extra->id, 'start_time' => '14:00']],
        ]));

        $response->assertSessionHasErrors('method');
        $this->assertSame(0, Appointment::count());
    }

    // Cash stays available when NOTHING is due online, and the single payment
    // is the whole visit (main + extra) with no proof uploaded.
    public function test_home_store_allows_cash_when_no_down_payment_is_due(): void
    {
        Storage::fake('public');

        $this->homeService->update(['down_payment' => null]);
        $extra = $this->makeHomeExtra('Extra No Down Payment', 400, 30, null);

        $response = $this->actingAs($this->client)->post(route('account.booking.home.store'), $this->homePayload([
            'method' => 'cash',
            'payment_proof' => null,
            'extras' => [['service_id' => $extra->id, 'start_time' => '14:00']],
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $appointment = Appointment::with('payment')->firstOrFail();
        $this->assertSame(AppointmentStatus::Unverified, $appointment->status);
        $this->assertSame('cash', $appointment->payment->method);
        $this->assertEquals(900 + 400, (float) $appointment->payment->amount);
        $this->assertNull($appointment->payment->proof_path);
    }

    // A branch-only service can never be slipped in as a Home extra.
    public function test_home_store_rejects_a_branch_only_extra_service(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->client)->post(route('account.booking.home.store'), $this->homePayload([
            'extras' => [['service_id' => $this->branchService->id, 'start_time' => '14:00']],
        ]));

        $response->assertSessionHasErrors('extras.0.service_id');
        $this->assertSame(0, Appointment::count());
    }

    // Packages remain a main-service choice only.
    public function test_home_store_rejects_a_package_as_extra(): void
    {
        Storage::fake('public');

        $package = $this->makeHomeExtra('Home Test Package', 2000, 60, null, 'Packages', 'home');

        $response = $this->actingAs($this->client)->post(route('account.booking.home.store'), $this->homePayload([
            'extras' => [['service_id' => $package->id, 'start_time' => '14:00']],
        ]));

        $response->assertSessionHasErrors('extras.0.service_id');
        $response->assertSessionHasErrors([
            'extras.0.service_id' => 'Packages cannot be added as extra services — pick a regular service.',
        ]);
        $this->assertSame(0, Appointment::count());
    }

    // The exact-slot collision check also covers the extra's personnel at the
    // extra's OWN start time.
    public function test_home_store_rejects_when_extra_personnel_is_already_booked_at_that_slot(): void
    {
        Storage::fake('public');

        $staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $extra = $this->makeHomeExtra('Extra Collision', 400, 30, null);
        $date = now()->addDay()->toDateString();

        Appointment::create([
            'user_id' => $this->clientB->id,
            'service_id' => $this->homeService->id,
            'personnel_id' => $staff->id,
            'appointment_date' => $date,
            'start_time' => '13:00',
            'status' => AppointmentStatus::Booked,
        ]);

        $response = $this->actingAs($this->client)->post(route('account.booking.home.store'), $this->homePayload([
            'appointment_date' => $date,
            'start_time' => '13:00',
            'extras' => [['service_id' => $extra->id, 'personnel_id' => $staff->id, 'start_time' => '13:00']],
        ]));

        $response->assertSessionHasErrors('extras.0.personnel_id');
        $response->assertSessionHasErrors(['extras.0.personnel_id' => 'That slot was just taken.']);
        $this->assertSame(1, Appointment::count());
    }

    // Each service's OWN window must fit business hours; extras no longer
    // extend the main service's window.
    public function test_home_booking_validates_each_service_window_against_business_hours(): void
    {
        Storage::fake('public');

        $this->homeService->update(['duration_minutes' => 60, 'down_payment' => null]);
        $extra = $this->makeHomeExtra('Extra Long Home Massage', 500, 120, null);
        $staff = User::where('email', 'staff@nexflio.test')->firstOrFail();

        $response = $this->actingAs($this->client)->post(route('account.booking.home.store'), $this->homePayload([
            'personnel_id' => (string) $staff->id,
            'start_time' => '20:00',
            'method' => 'cash',
            'payment_proof' => null,
            'extras' => [['service_id' => $extra->id, 'start_time' => '21:00']],
        ]));

        $response->assertSessionHasErrors('extras.0.start_time');
        $this->assertSame(0, Appointment::count());
    }
}
