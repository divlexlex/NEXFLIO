<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownPaymentPerServiceTest extends TestCase
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

    private function service(string $name, float $price, ?float $downPayment = 20.0, string $category = 'Massage'): Service
    {
        return Service::create([
            'name' => $name,
            'category' => $category,
            'description' => 'Test service',
            'price' => $price,
            'down_payment' => $downPayment,
            'duration_minutes' => 60,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);
    }

    private function payload(Service $service, array $overrides = []): array
    {
        return array_merge([
            'service_id' => $service->id,
            'personnel_id' => 'any',
            'appointment_date' => $this->date,
            'start_time' => '13:00',
            'method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ], $overrides);
    }

    public function test_new_services_default_to_twenty_and_null_disables_it(): void
    {
        $default = Service::create([
            'name' => 'Default DP Probe',
            'category' => 'Massage',
            'description' => 'Test',
            'price' => 500,
            'duration_minutes' => 60,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);

        $none = $this->service('No DP Probe', 500, null);

        $this->assertSame('20.00', (string) $default->fresh()->down_payment);
        $this->assertNull($none->fresh()->down_payment);

        $this->assertSame(20.0, \App\Support\BookingExtras::downPaymentForService($default->fresh(), 500));
        $this->assertSame(0.0, \App\Support\BookingExtras::downPaymentForService($none->fresh(), 500));
    }

    public function test_online_payment_uses_configured_down_payment_and_caps_at_price(): void
    {
        Storage::fake('public');

        $regular = $this->service('Configured DP Probe', 800, 225.50);
        $cheap = $this->service('Capped DP Probe', 300, 500);

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->payload($regular)
        )->assertRedirect()->assertSessionHasNoErrors();

        $regularAppointment = Appointment::latest('id')->firstOrFail();
        $this->assertEquals(225.50, (float) $regularAppointment->payment->amount);
        $this->assertEquals(800, (float) $regularAppointment->booked_price);

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->payload($cheap, ['start_time' => '14:00'])
        )->assertRedirect()->assertSessionHasNoErrors();

        $cappedAppointment = Appointment::latest('id')->firstOrFail();
        $this->assertEquals(300, (float) $cappedAppointment->payment->amount);
        $this->assertEquals(300, (float) $cappedAppointment->booked_price);
    }

    public function test_no_down_payment_allows_cash_or_full_online_payment(): void
    {
        Storage::fake('public');

        $service = $this->service('No DP Booking Probe', 500, null);

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->payload($service, ['method' => 'cash', 'payment_proof' => null])
        )->assertRedirect()->assertSessionHasNoErrors();

        $cash = Appointment::latest('id')->firstOrFail();
        $this->assertSame(AppointmentStatus::Unverified, $cash->status);
        $this->assertSame('cash', $cash->payment->method);
        $this->assertEquals(500, (float) $cash->payment->amount);

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->payload($service, ['start_time' => '14:00'])
        )->assertRedirect()->assertSessionHasNoErrors();

        $online = Appointment::latest('id')->firstOrFail();
        $this->assertSame('gcash', $online->payment->method);
        $this->assertEquals(500, (float) $online->payment->amount);
    }

    public function test_extras_each_contribute_their_own_down_payment(): void
    {
        Storage::fake('public');

        $main = $this->service('Multi DP Main', 500, 100);
        $firstExtra = $this->service('Multi DP Extra One', 300, 50);
        $secondExtra = $this->service('Multi DP Extra Two', 200, null);

        $this->actingAs($this->client)->post(
            route('account.booking.branch.store'),
            $this->payload($main, [
                'extras' => [
                    ['service_id' => $firstExtra->id],
                    ['service_id' => $secondExtra->id],
                ],
            ])
        )->assertRedirect()->assertSessionHasNoErrors();

        $appointment = Appointment::with('addedServices')->latest('id')->firstOrFail();

        $this->assertEquals(1000, (float) $appointment->booked_price);
        $this->assertEquals(150, (float) $appointment->payment->amount);
        $this->assertSame(2, $appointment->addedServices->count());
    }

    public function test_admin_can_create_update_and_clear_a_service_down_payment(): void
    {
        $payload = [
            'name' => 'Admin DP Probe',
            'category' => 'Facial',
            'description' => 'Test',
            'price' => 700,
            'down_payment' => 125.50,
            'duration_minutes' => 60,
            'status' => 'active',
        ];

        $this->actingAs($this->manager)
            ->post(route('admin.services.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $service = Service::where('name', 'Admin DP Probe')->firstOrFail();
        $this->assertSame('125.50', (string) $service->down_payment);

        $this->actingAs($this->manager)
            ->patch(route('admin.services.update', $service->id), array_merge($payload, ['down_payment' => '']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($service->fresh()->down_payment);

        $this->actingAs($this->manager)
            ->patch(route('admin.services.update', $service->id), array_merge($payload, ['down_payment' => 300]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('300.00', (string) $service->fresh()->down_payment);
    }

    public function test_admin_catalogs_expose_the_configured_down_payment(): void
    {
        $service = $this->service('Catalog DP Probe', 600, 175.25);

        foreach ([route('admin.appointments'), route('admin.payments', ['status' => 'all'])] as $url) {
            $html = $this->actingAs($this->manager)->get($url)->assertOk()->getContent();

            preg_match('/const servicesCatalog = (\[.*?\]);/s', $html, $matches);
            $this->assertNotEmpty($matches, 'servicesJson catalog missing from '.$url);

            $catalog = collect(json_decode($matches[1], true))->keyBy('name');
            $this->assertArrayHasKey($service->name, $catalog->all());
            $this->assertEquals(175.25, (float) $catalog[$service->name]['down_payment']);
        }

        $html = $this->actingAs($this->manager)->get(route('admin.services'))->getContent();
        $this->assertStringContainsString('id="svc-down-payment"', $html);
        $this->assertStringContainsString('data-down-payment="175.25"', $html);
    }

    public function test_service_detail_shows_main_and_extra_down_payments(): void
    {
        $main = $this->service('Detail DP Main', 500, 20);
        $this->service('Detail DP Extra', 300, 50.5);
        $this->service('Detail No DP Extra', 200, null);

        $html = $this->actingAs($this->client)
            ->get(route('account.booking.branch.service.show', $main->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Down payment: ₱20.00', $html);
        $this->assertStringContainsString('"down_payment":50.5', $html);
        $this->assertStringContainsString('"down_payment":null', $html);

        $noDpMain = $this->service('Detail No DP Main', 500, null);

        $this->actingAs($this->client)
            ->get(route('account.booking.branch.service.show', $noDpMain->id))
            ->assertOk()
            ->assertSee('Down payment: none');
    }

    public function test_payments_card_shows_only_recorded_addition_down_payments(): void
    {
        $main = $this->service('Card DP Main', 500, null);
        $extra = $this->service('Card DP Extra', 400, 75);

        $appointment = Appointment::create([
            'walk_in_name' => 'Card DP Guest',
            'service_id' => $main->id,
            'personnel_id' => null,
            'appointment_date' => $this->date,
            'start_time' => '10:00',
            'booked_price' => (float) $main->price,
            'status' => AppointmentStatus::Booked,
        ]);

        $this->actingAs($this->manager)->post(
            route('admin.appointments.additional-services.store', $appointment->id),
            [
                'service_id' => $extra->id,
                'down_payment' => 40,
                'method' => 'cash',
            ]
        )->assertRedirect()->assertSessionHas('success');

        $html = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Down payment: ₱40.00', $html);
        $this->assertStringNotContainsString('Down payment: ₱75.00', $html);
    }
}
