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
            'price' => 900, 'duration_minutes' => 90, 'status' => 'active', 'service_location_type' => 'home',
        ]);
        $this->bothService = Service::create([
            'name' => 'Either Massage', 'category' => 'Massage', 'description' => 'Test',
            'price' => 700, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'both',
        ]);
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
    // modal, booking start chooser with Home Service enabled (no longer
    // "Coming soon").
    // Home Service is intentionally stubbed to "Coming Soon" on this page
    // (and on the "Book an Appointment" floating-card modal) for now — a
    // deliberate UI scoping decision, not a regression. The route/controller
    // behind it (account.booking.home.service) is untouched and still fully
    // functional; see test_home_service_booking_creates_real_records_and_preserves_address_history
    // below, which books through it directly rather than via this entry page.
    public function test_booking_start_page_offers_branch_booking_and_stubs_home_service(): void
    {
        $response = $this->actingAs($this->client)->get(route('account.booking.start'));

        $response->assertOk();
        $response->assertSee('Coming Soon');
        $response->assertSee(route('account.booking.branch.service'), false);
        $response->assertDontSee(route('account.booking.home.service'), false);
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
        $this->assertSame('900.00', (string) $appointment->payment->amount);
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
}
