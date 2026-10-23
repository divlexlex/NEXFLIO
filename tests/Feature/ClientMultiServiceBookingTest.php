<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentAddedService;
use App\Models\Service;
use App\Models\User;
use App\Support\ServiceCapacity;
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
                ['service_id' => $manicure->id, 'start_time' => '14:00'],
                ['service_id' => $tiered->id, 'variant_label' => 'Deluxe', 'start_time' => '15:00'],
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
        $this->assertSame('14:00', $byService[$manicure->id]->start_time->format('H:i'));
        $this->assertEquals(1200, (float) $byService[$tiered->id]->price);
        $this->assertSame('Deluxe', $byService[$tiered->id]->variant_label);
        $this->assertSame('15:00', $byService[$tiered->id]->start_time->format('H:i'));

        // Product rule: extras are no longer picked on the service-detail step.
        // The list builder (add-another popups) lives on the Schedule step and
        // its catalog never offers packages.
        $this->actingAs($this->client)
            ->get(route('account.booking.branch.service.show', $main->id))
            ->assertOk()
            ->assertDontSee('Avail Services')
            ->assertDontSee('data-extra-service', false);

        $this->actingAs($this->client)
            ->get(route('account.booking.branch.schedule', [
                'service' => $main->id,
                'date' => $this->date,
                'personnel' => 'any',
            ]))
            ->assertOk()
            ->assertSee('Add another service')
            ->assertSee('id="servicePickerModal"', false)
            ->assertSee('id="addAnotherModal"', false)
            ->assertSee('id="svcCardMain"', false)
            ->assertSee('id="scheduleActionBar"', false)
            ->assertSee('data-personnel-name', false)
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
                ['service_id' => $manicure->id, 'start_time' => '14:00'],
                ['service_id' => $facial->id, 'start_time' => '15:00'],
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
                ['service_id' => $manicure->id, 'start_time' => '15:00'],
                ['service_id' => $facial->id, 'start_time' => '16:00'],
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
            'extras' => [['service_id' => $manicure->id, 'start_time' => '14:00', 'price' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertEquals(399, (float) $appointment->addedServices->first()->price);

        // Package as an extra is refused.
        $this->store([
            'start_time' => '15:00',
            'extras' => [['service_id' => $package->id, 'variant_label' => 'Solo', 'start_time' => '15:00']],
        ])->assertSessionHasErrors('extras.0.service_id');

        $this->assertSame(1, Appointment::count());
        $this->assertSame(1, AppointmentAddedService::count());

        // Invalid variant on a variant service is refused.
        $tiered = $this->service('Extra Variant Service', 'Facial', 800, 45);
        $tiered->variants()->create(['label' => 'Deluxe', 'price' => 1200, 'sort' => 1]);

        $this->store([
            'start_time' => '16:00',
            'extras' => [['service_id' => $tiered->id, 'variant_label' => 'Nope', 'start_time' => '16:00']],
        ])->assertSessionHasErrors('extras.0.variant_label');

        $this->assertSame(1, Appointment::count());
        $this->assertSame(1, AppointmentAddedService::count());
    }

    // 4. Each service's OWN window must fit closing time. The extras no
    // longer extend the main service's window; they are validated separately.
    public function test_each_service_window_is_validated_against_closing(): void
    {
        $long = $this->service('Extra Long Massage', 'Massage', 500, 120);
        $short = $this->service('Extra Short Massage', 'Massage', 300, 30);
        $staff = User::where('email', 'staff@nexflio.test')->firstOrFail();

        // Main 20:00-21:00 is fine, but its own 21:00 start runs to 23:00.
        $this->store([
            'personnel_id' => (string) $staff->id,
            'start_time' => '20:00',
            'extras' => [['service_id' => $long->id, 'start_time' => '21:00']],
        ])->assertSessionHasErrors('extras.0.start_time');

        $this->assertSame(0, Appointment::count());

        // Same main, but the short extra at 21:00 ends 21:30, before closing.
        $this->store([
            'personnel_id' => (string) $staff->id,
            'start_time' => '20:00',
            'extras' => [['service_id' => $short->id, 'start_time' => '21:00']],
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
            'extras' => [['service_id' => $manicure->id, 'start_time' => '14:00']],
        ])->assertSessionHasErrors('method');

        $this->assertSame(0, Appointment::count());

        $main->update(['down_payment' => null]);
        $manicure->update(['down_payment' => null]);

        $this->store([
            'method' => 'cash',
            'payment_proof' => null,
            'extras' => [['service_id' => $manicure->id, 'start_time' => '14:00']],
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

    // 7. Each extra keeps its own personnel and start time, and the exact-slot
    // conflict check runs against the extra's own window.
    public function test_extra_personnel_and_start_time_are_persisted_and_collision_checked(): void
    {
        $staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $main = Service::where('name', 'Swedish Massage')->firstOrFail();
        $extra = $this->service('Extra Focus Massage', 'Massage', 300, 30);

        $this->store([
            'personnel_id' => (string) $staff->id,
            'start_time' => '13:00',
            'extras' => [[
                'service_id' => $extra->id,
                'personnel_id' => $staff->id,
                'start_time' => '14:00',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $row = Appointment::latest('id')->firstOrFail()->addedServices()->firstOrFail();
        $this->assertSame($staff->id, $row->personnel_id);
        $this->assertSame('14:00', $row->start_time->format('H:i'));

        // A second request whose extra collides with the first main service's
        // staff/window is rejected on the extra's own personnel key.
        $this->store([
            'start_time' => '15:00',
            'extras' => [[
                'service_id' => $extra->id,
                'personnel_id' => $staff->id,
                'start_time' => '13:00',
            ]],
        ])->assertSessionHasErrors([
            'extras.0.personnel_id' => 'That slot was just taken.',
        ]);

        $this->assertSame(1, Appointment::count());
        $this->assertSame(1, AppointmentAddedService::count());
        $this->assertSame($main->id, Appointment::firstOrFail()->service_id);
    }

    // 8. A booking-added row occupies branch capacity at ITS own window, not
    // just the parent appointment's window.
    public function test_booking_added_service_occupies_its_own_capacity_window(): void
    {
        $main = $this->service('Capacity Facial Main', 'Facial', 500, 30);
        $extra = $this->service('Capacity Facial Extra', 'Facial', 300, 30);

        $this->store([
            'service_id' => $main->id,
            'start_time' => '10:00',
            'extras' => [['service_id' => $extra->id, 'start_time' => '12:00']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $response = $this->store([
            'service_id' => $main->id,
            'start_time' => '12:00',
        ]);

        $response->assertSessionHasErrors([
            'start_time' => ServiceCapacity::denialMessage($main),
        ]);
        $this->assertSame(1, Appointment::count());
    }

    // 9. Legacy walk-in creation and Payments rows have NULL start_time and
    // must never claim their own capacity window.
    public function test_creation_and_payment_rows_without_start_time_do_not_consume_capacity(): void
    {
        $facial = $this->service('Capacity Legacy Facial', 'Facial', 300, 30);

        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $facial->id,
            'personnel_id' => null,
            'appointment_date' => $this->date,
            'start_time' => '10:00',
            'status' => AppointmentStatus::Booked,
        ]);

        foreach (['creation', 'payment'] as $source) {
            AppointmentAddedService::create([
                'appointment_id' => $appointment->id,
                'service_id' => $facial->id,
                'price' => 300,
                'personnel_id' => null,
                'start_time' => null,
                'added_by' => $this->manager->id,
                'source' => $source,
            ]);
        }

        $this->store([
            'service_id' => $facial->id,
            'start_time' => '12:00',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, Appointment::count());
        $this->assertSame(2, AppointmentAddedService::whereNull('start_time')->count());
    }

    // 10. My Bookings lists every extra; the Payments card gives booking
    // extras no "Added" tag and no DP sub-line.
    public function test_booking_extras_render_in_my_bookings_and_without_payment_tags(): void
    {
        $manicure = Service::where('name', 'Gel Manicure')->firstOrFail();
        $facial = $this->service('Extra Facial Visible', 'Facial', 800, 60);

        $this->store([
            'extras' => [
                ['service_id' => $manicure->id, 'start_time' => '14:00'],
                ['service_id' => $facial->id, 'start_time' => '15:00'],
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
