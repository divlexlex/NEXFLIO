<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentAddedService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Problem #4 — a Client books MULTIPLE services in ONE online branch booking
 * transaction. Extras live on the SAME appointment (same reference code) as
 * appointment_added_services rows with source='booking', and the single
 * down payment is the sum of each booked service's own configured amount.
 */
class ClientMultiServiceBookingTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    private User $manager;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->date = now()->addDay()->toDateString();
    }

    private function service(string $name, string $category, float $price, int $duration = 60, string $location = 'branch'): Service
    {
        return Service::create([
            'name' => $name,
            'category' => $category,
            'description' => 'Test service',
            'price' => $price,
            'duration_minutes' => $duration,
            'status' => 'active',
            'service_location_type' => $location,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'service_id' => Service::where('name', 'Swedish Massage')->firstOrFail()->id,
            'personnel_id' => 'any',
            'appointment_date' => $this->date,
            'start_time' => '13:00',
            'method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ], $overrides);
    }

    private function store(array $overrides = [])
    {
        Storage::fake('public');

        return $this->actingAs($this->client)
            ->post(route('account.booking.branch.store'), $this->payload($overrides));
    }

    // 1. Main + 2 extras (one via variant) -> ONE appointment, ONE reference
    // code, 2 source='booking' rows with server-resolved prices.
    public function test_main_plus_two_extras_creates_one_appointment_and_booking_rows(): void
    {
        $main = Service::where('name', 'Swedish Massage')->firstOrFail();      // 599
        $manicure = Service::where('name', 'Gel Manicure')->firstOrFail();     // 399

        $tiered = $this->service('Extra Tiered Facial', 'Facial', 800, 45);
        $tiered->variants()->create(['label' => 'Deluxe', 'price' => 1200, 'sort' => 1]);

        $this->store([
            'service_id' => $main->id,
            'extras' => [
                ['service_id' => $manicure->id],
                ['service_id' => $tiered->id, 'variant_label' => 'Deluxe'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, Appointment::count());
        $appointment = Appointment::with('addedServices')->latest('id')->firstOrFail();

        $this->assertNotNull($appointment->reference_code);
        $this->assertSame($appointment->referenceCode(), $appointment->fresh()->referenceCode());
        $this->assertEquals(599 + 399 + 1200, (float) $appointment->booked_price);

        $this->assertSame(2, $appointment->addedServices->count());
        foreach ($appointment->addedServices as $row) {
            $this->assertSame('booking', $row->source);
            $this->assertSame($this->client->id, $row->added_by);
        }

        $byService = $appointment->addedServices->keyBy('service_id');
        $this->assertEquals(399, (float) $byService[$manicure->id]->price);
        $this->assertNull($byService[$manicure->id]->variant_label);
        $this->assertEquals(1200, (float) $byService[$tiered->id]->price);
        $this->assertSame('Deluxe', $byService[$tiered->id]->variant_label);

        // Wizard UI: the "+ Add another service" control is offered and the
        // extras catalog never offers packages.
        $this->actingAs($this->client)
            ->get(route('account.booking.branch.service.show', $main->id))
            ->assertOk()
            ->assertSee('Add another service')
            ->assertDontSee('"name":"Perfect Package"', false);
    }

    // 2. DP math: each booked service contributes its own configured amount.
    public function test_down_payment_sums_each_service_configuration(): void
    {
        $main = Service::where('name', 'Swedish Massage')->firstOrFail();       // 599 -> 300
        $manicure = Service::where('name', 'Gel Manicure')->firstOrFail();     // 399 -> 199.50
        $facial = $this->service('Extra Facial 800', 'Facial', 800, 60);       // -> 400.00

        $main->update(['down_payment' => 300]);
        $manicure->update(['down_payment' => 199.50]);
        $facial->update(['down_payment' => 400]);

        $this->store([
            'extras' => [
                ['service_id' => $manicure->id],
                ['service_id' => $facial->id],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertEquals(899.50, (float) $appointment->payment->amount);
        $this->assertEquals(599 + 399 + 800, (float) $appointment->booked_price);

        // Package main: its own configured ₱500 DP joins the extras' amounts.
        $package = Service::where('name', 'Package LIZ')->firstOrFail();
        $package->update(['down_payment' => 500]);
        $couple = $package->variants()->where('label', 'Couple')->firstOrFail();

        $this->store([
            'service_id' => $package->id,
            'variant_id' => $couple->id,
            'start_time' => '14:00',
            'extras' => [
                ['service_id' => $manicure->id],
                ['service_id' => $facial->id],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $packageAppointment = Appointment::latest('id')->firstOrFail();
        $this->assertEquals(3699 + 399 + 800, (float) $packageAppointment->booked_price);
        $this->assertEquals(500 + 199.50 + 400, (float) $packageAppointment->payment->amount);
    }

    // 3. Bogus client-sent price ignored; package extra refused; invalid
    // variant refused; refused booking writes nothing.
    public function test_extras_are_server_validated_and_refusals_write_nothing(): void
    {
        $manicure = Service::where('name', 'Gel Manicure')->firstOrFail();
        $package = Service::where('name', 'Perfect Package')->firstOrFail();

        // Client-sent price is ignored.
        $this->store([
            'extras' => [['service_id' => $manicure->id, 'price' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertEquals(399, (float) $appointment->addedServices->first()->price);

        // Package as an extra is refused.
        $this->store([
            'start_time' => '15:00',
            'extras' => [['service_id' => $package->id, 'variant_label' => 'Solo']],
        ])->assertSessionHasErrors('extras.0.service_id');

        $this->assertSame(1, Appointment::count());
        $this->assertSame(1, AppointmentAddedService::count());

        // Invalid variant on a variant service is refused.
        $tiered = $this->service('Extra Variant Service', 'Facial', 800, 45);
        $tiered->variants()->create(['label' => 'Deluxe', 'price' => 1200, 'sort' => 1]);

        $this->store([
            'start_time' => '16:00',
            'extras' => [['service_id' => $tiered->id, 'variant_label' => 'Nope']],
        ])->assertSessionHasErrors('extras.0.variant_label');

        $this->assertSame(1, Appointment::count());
        $this->assertSame(1, AppointmentAddedService::count());
    }

    // 4. Visit length = main + Σ extras drives the business-hours window.
    public function test_total_duration_past_closing_is_refused_and_shorter_extra_succeeds(): void
    {
        $long = $this->service('Extra Long Massage', 'Massage', 500, 120);
        $short = $this->service('Extra Short Massage', 'Massage', 300, 30);
        $staff = User::where('email', 'staff@nexflio.test')->firstOrFail();

        // 60 + 120 = 180 mins from 20:00 -> 23:00, past the 22:00 close.
        $this->store([
            'personnel_id' => (string) $staff->id,
            'start_time' => '20:00',
            'extras' => [['service_id' => $long->id]],
        ])->assertSessionHasErrors('start_time');

        $this->assertSame(0, Appointment::count());

        // 60 + 30 = 90 mins from 20:00 -> 21:30, fits.
        $this->store([
            'personnel_id' => (string) $staff->id,
            'start_time' => '20:00',
            'extras' => [['service_id' => $short->id]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, Appointment::count());
    }

    // 5. Cash: refused when any booked service has a DP; allowed when all
    // are configured with none (package or not).
    public function test_cash_rules_follow_configured_down_payments(): void
    {
        $main = Service::where('name', 'Swedish Massage')->firstOrFail();
        $manicure = Service::where('name', 'Gel Manicure')->firstOrFail();
        $package = Service::where('name', 'Package LIZ')->firstOrFail();
        $package->update(['down_payment' => 500]);
        $couple = $package->variants()->where('label', 'Couple')->firstOrFail();

        $this->store([
            'service_id' => $package->id,
            'variant_id' => $couple->id,
            'method' => 'cash',
            'payment_proof' => null,
            'extras' => [['service_id' => $manicure->id]],
        ])->assertSessionHasErrors('method');

        $this->assertSame(0, Appointment::count());

        $main->update(['down_payment' => null]);
        $manicure->update(['down_payment' => null]);

        $this->store([
            'method' => 'cash',
            'payment_proof' => null,
            'extras' => [['service_id' => $manicure->id]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertSame('cash', $appointment->payment->method);
        $this->assertEquals(599 + 399, (float) $appointment->payment->amount);
    }

    // 6. Single-service booking is unchanged; home-flow ignores extras.
    public function test_single_service_and_home_flow_are_unchanged(): void
    {
        $this->store()->assertRedirect()->assertSessionHasNoErrors();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertEquals(599, (float) $appointment->booked_price);
        $this->assertEquals(20, (float) $appointment->payment->amount);
        $this->assertSame(0, AppointmentAddedService::count());

        $home = $this->service('Home Test Massage', 'Home Service', 900, 90, 'home');

        Storage::fake('public');
        $this->actingAs($this->client)->post(route('account.booking.home.store'), [
            'service_id' => $home->id,
            'personnel_id' => 'any',
            'appointment_date' => $this->date,
            'start_time' => '13:00',
            'method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->image('home.jpg'),
            'street_address' => '123 Test St',
            'barangay' => 'Barangay 1',
            'city_municipality' => 'Test City',
            'province' => 'Test Province',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $homeAppointment = Appointment::latest('id')->firstOrFail();
        $this->assertEquals(900, (float) $homeAppointment->booked_price);
        $this->assertEquals(20, (float) $homeAppointment->payment->amount);
        $this->assertSame(0, $homeAppointment->addedServices()->count());
    }

    // 7. My Bookings lists every extra; the Payments card gives booking
    // extras no "Added" tag and no DP sub-line.
    public function test_booking_extras_render_in_my_bookings_and_without_payment_tags(): void
    {
        $manicure = Service::where('name', 'Gel Manicure')->firstOrFail();
        $facial = $this->service('Extra Facial Visible', 'Facial', 800, 60);

        $this->store([
            'extras' => [
                ['service_id' => $manicure->id],
                ['service_id' => $facial->id],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->client)
            ->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Gel Manicure')
            ->assertSee('Extra Facial Visible');

        $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Gel Manicure')
            ->assertSee('Extra Facial Visible')
            ->assertDontSee('>Added<', false)
            ->assertDontSee('Down payment (50%)');
    }
}
