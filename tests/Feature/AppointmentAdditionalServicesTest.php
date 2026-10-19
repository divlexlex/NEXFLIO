<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\AppointmentAddedService;
use App\Models\Commission;
use App\Models\Position;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Services added to an existing appointment (same Appointment ID): the drawer
 * endpoints, price snapshot, balance effect, personnel eligibility, removal
 * guards, and the commission settlement at completion.
 */
class AppointmentAdditionalServicesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;

    private User $massageStaff;

    private User $facialStaff;

    private User $nailStaff;

    private Service $massage;

    private Service $facial;

    private Service $home;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->massageStaff = $this->makeStaff('Massage Technician', 'addon.massage@nexflio.test');
        $this->facialStaff = $this->makeStaff('Facial Technician', 'addon.facial@nexflio.test');
        $this->nailStaff = $this->makeStaff('Nail Technician', 'addon.nail@nexflio.test');

        $this->massage = $this->service('Addon Massage Probe', 'Massage', 500);
        $this->facial = $this->service('Addon Facial Probe', 'Facial', 300);
        // "Home Service" is a real category with NO linked position → nobody
        // is eligible for it.
        $this->home = $this->service('Addon Home Probe', 'Home Service', 200);

        $this->date = now()->toDateString();
    }

    private function makeStaff(string $position, string $email): User
    {
        $user = User::create([
            'name' => ucfirst(explode(' ', $position)[0]).' Addon',
            'email' => $email,
            'role_id' => User::ROLE_STAFF,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        StaffProfile::create([
            'user_id' => $user->id,
            'base_pay' => 12000,
            'commission_rate' => 10.00,
            'position' => $position,
            'employment_status' => 'active',
            'hired_at' => now()->toDateString(),
        ]);

        return $user;
    }

    private function service(string $name, string $category, float $price): Service
    {
        return Service::create([
            'name' => $name,
            'category' => $category,
            'description' => 'Test service',
            'price' => $price,
            'duration_minutes' => 60,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);
    }

    private function bookAppointment(
        Service $service,
        AppointmentStatus $status = AppointmentStatus::Booked,
        ?User $personnel = null,
        ?User $client = null,
    ): Appointment {
        return Appointment::create([
            'user_id' => $client?->id,
            'walk_in_name' => $client ? null : 'Addon Guest',
            'service_id' => $service->id,
            'personnel_id' => ($personnel ?? $this->massageStaff)->id,
            'appointment_date' => $this->date,
            'start_time' => '10:00',
            'booked_price' => (float) $service->price,
            'status' => $status,
        ]);
    }

    private function add(int $appointmentId, array $payload)
    {
        return $this->actingAs($this->manager)
            ->from(route('admin.appointments'))
            ->post(route('admin.appointments.additional-services.store', $appointmentId), $payload);
    }

    public function test_add_to_booked_appointment_raises_price_and_balance_due(): void
    {
        $appointment = $this->bookAppointment($this->massage);

        $this->add($appointment->id, [
            'service_id' => $this->facial->id,
            'personnel_id' => $this->facialStaff->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('appointment_added_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $this->facial->id,
            'price' => 300.00,
            'added_by' => $this->manager->id,
            'personnel_id' => $this->facialStaff->id,
            'source' => 'payment',
        ]);

        $appointment->refresh();
        $this->assertSame(800.0, (float) $appointment->booked_price);
        $this->assertSame(800.0, $appointment->balanceDue());
    }

    public function test_add_succeeds_on_in_service_appointment(): void
    {
        $appointment = $this->bookAppointment($this->massage, AppointmentStatus::InService);

        $this->add($appointment->id, ['service_id' => $this->facial->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('appointment_added_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $this->facial->id,
        ]);
    }

    public function test_add_refused_for_non_modifiable_statuses(): void
    {
        foreach ([
            AppointmentStatus::Unverified,
            AppointmentStatus::Completed,
            AppointmentStatus::Cancelled,
            AppointmentStatus::NoShow,
        ] as $status) {
            $appointment = $this->bookAppointment($this->massage, $status);

            $this->add($appointment->id, ['service_id' => $this->facial->id])
                ->assertRedirect()
                ->assertSessionHas('error');

            $this->assertDatabaseMissing('appointment_added_services', [
                'appointment_id' => $appointment->id,
            ]);

            $appointment->refresh();
            $this->assertSame(500.0, (float) $appointment->booked_price);
        }

        $this->assertSame(0, AppointmentAddedService::count());
    }

    public function test_variant_price_is_snapshotted_and_invalid_variant_refused(): void
    {
        $variantService = $this->service('Addon Variant Probe', 'Massage', 700);
        $variantService->variants()->create(['label' => 'Solo', 'price' => 600, 'guests' => 1, 'sort' => 0]);
        $variantService->variants()->create(['label' => 'Couple', 'price' => 1100, 'guests' => 2, 'sort' => 1]);

        $appointment = $this->bookAppointment($this->massage);

        $this->add($appointment->id, [
            'service_id' => $variantService->id,
            'variant_label' => 'Couple',
            'personnel_id' => $this->massageStaff->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('appointment_added_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $variantService->id,
            'variant_label' => 'Couple',
            'price' => 1100.00,
        ]);

        // A variant service with no / an unknown variant is refused.
        $this->add($appointment->id, [
            'service_id' => $variantService->id,
            'variant_label' => 'Nope',
        ])->assertSessionHas('error');

        $this->assertSame(1, AppointmentAddedService::count());
    }

    public function test_packages_refused_and_client_price_ignored(): void
    {
        $package = $this->service('Addon Package Probe', 'Packages', 900);
        $package->positions()->attach(Position::where('name', 'Massage Technician')->firstOrFail()->id);

        $appointment = $this->bookAppointment($this->massage);

        $this->add($appointment->id, ['service_id' => $package->id])
            ->assertSessionHas('error');
        $this->assertSame(0, AppointmentAddedService::count());

        // A client-sent price is ignored — the catalog price is used.
        $this->add($appointment->id, [
            'service_id' => $this->facial->id,
            'price' => 1,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('appointment_added_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $this->facial->id,
            'price' => 300.00,
        ]);
    }

    public function test_personnel_eligibility_rules(): void
    {
        $appointment = $this->bookAppointment($this->massage);

        // Ineligible: a Nail Technician cannot do a Facial-category service.
        $this->add($appointment->id, [
            'service_id' => $this->facial->id,
            'personnel_id' => $this->nailStaff->id,
        ])->assertSessionHas('error');
        $this->assertDatabaseMissing('appointment_added_services', ['appointment_id' => $appointment->id]);

        // Eligible staff is stored.
        $this->add($appointment->id, [
            'service_id' => $this->facial->id,
            'personnel_id' => $this->facialStaff->id,
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('appointment_added_services', [
            'appointment_id' => $appointment->id,
            'personnel_id' => $this->facialStaff->id,
        ]);

        // A category with no linked positions rejects ANY personnel…
        $this->add($appointment->id, [
            'service_id' => $this->home->id,
            'personnel_id' => $this->massageStaff->id,
        ])->assertSessionHas('error');

        // …but accepts the service with no personnel set.
        $this->add($appointment->id, ['service_id' => $this->home->id])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('appointment_added_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $this->home->id,
            'personnel_id' => null,
        ]);
    }

    public function test_remove_decrements_price_and_is_refused_below_collected_money(): void
    {
        // Successful removal.
        $appointment = $this->bookAppointment($this->massage);
        $this->add($appointment->id, ['service_id' => $this->facial->id]);
        $added = AppointmentAddedService::where('appointment_id', $appointment->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->from(route('admin.appointments'))
            ->delete(route('admin.appointments.additional-services.destroy', [
                'appointment' => $appointment->id,
                'added' => $added->id,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('appointment_added_services', ['id' => $added->id]);
        $appointment->refresh();
        $this->assertSame(500.0, (float) $appointment->booked_price);

        // Refused removal: money already collected exceeds the remainder.
        $guarded = $this->bookAppointment($this->massage);
        $this->add($guarded->id, ['service_id' => $this->facial->id]);
        $guarded->payments()->create([
            'kind' => 'branch',
            'amount' => 600,
            'method' => 'cash',
            'status' => PaymentStatus::Verified,
            'verified_by' => $this->manager->id,
            'verified_at' => now(),
        ]);

        $added = AppointmentAddedService::where('appointment_id', $guarded->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->from(route('admin.appointments'))
            ->delete(route('admin.appointments.additional-services.destroy', [
                'appointment' => $guarded->id,
                'added' => $added->id,
            ]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('appointment_added_services', ['id' => $added->id]);
        $guarded->refresh();
        $this->assertSame(800.0, (float) $guarded->booked_price);
    }

    public function test_completion_pays_commissions_for_main_and_added_services(): void
    {
        $appointment = $this->bookAppointment($this->massage, AppointmentStatus::InService, $this->massageStaff);
        $this->add($appointment->id, [
            'service_id' => $this->facial->id,
            'personnel_id' => $this->facialStaff->id,
        ]);

        app(AppointmentService::class)->transition(
            $appointment->fresh(['service', 'personnel.staffProfile']),
            AppointmentStatus::Completed,
            $this->manager,
        );

        // Main: ₱500 × 10% = ₱50 (massage staff).
        $this->assertDatabaseHas('commissions', [
            'appointment_id' => $appointment->id,
            'user_id' => $this->massageStaff->id,
            'included_service_id' => $this->massage->id,
            'amount' => 50.00,
        ]);

        // Added: ₱300 × 10% = ₱30 (facial staff).
        $this->assertDatabaseHas('commissions', [
            'appointment_id' => $appointment->id,
            'user_id' => $this->facialStaff->id,
            'included_service_id' => $this->facial->id,
            'amount' => 30.00,
        ]);
    }

    public function test_collision_same_staff_and_service_pays_twice_in_one_row(): void
    {
        // Main booking and the added service are the SAME service performed by
        // the SAME staff member.
        $appointment = $this->bookAppointment($this->massage, AppointmentStatus::InService, $this->massageStaff);
        $this->add($appointment->id, [
            'service_id' => $this->massage->id,
            'personnel_id' => $this->massageStaff->id,
        ]);

        app(AppointmentService::class)->transition(
            $appointment->fresh(['service', 'personnel.staffProfile']),
            AppointmentStatus::Completed,
            $this->manager,
        );

        // One immutable row, amount covers both performances: 50 + 50 = 100.
        $this->assertSame(1, Commission::where('appointment_id', $appointment->id)
            ->where('user_id', $this->massageStaff->id)
            ->where('included_service_id', $this->massage->id)
            ->count());

        $this->assertDatabaseHas('commissions', [
            'appointment_id' => $appointment->id,
            'user_id' => $this->massageStaff->id,
            'included_service_id' => $this->massage->id,
            'amount' => 100.00,
        ]);
    }

    public function test_receipt_and_my_bookings_show_added_service(): void
    {
        $client = User::where('email', 'client@nexflio.test')->firstOrFail();

        $appointment = $this->bookAppointment(
            $this->massage,
            AppointmentStatus::Completed,
            $this->massageStaff,
            $client,
        );
        $appointment->addedServices()->create([
            'service_id' => $this->facial->id,
            'price' => 300,
            'added_by' => $this->manager->id,
        ]);

        $this->actingAs($this->manager)
            ->get(route('admin.appointments.receipt', $appointment))
            ->assertOk()
            ->assertSee('Addon Facial Probe');

        $this->actingAs($client)
            ->get(route('account.bookings'))
            ->assertOk()
            ->assertSee('Addon Facial Probe');
    }

    public function test_add_service_control_lives_on_payments_for_booked_and_in_service(): void
    {
        // The Appointments drawer keeps a read-only list — no add trigger, and
        // the shared add modal is no longer even present there.
        $appointmentsHtml = $this->actingAs($this->manager)
            ->get(route('admin.appointments'))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('dw-add-service', $appointmentsHtml);
        $this->assertStringNotContainsString('data-bs-target="#addedServiceModal"', $appointmentsHtml);

        // Both Booked and In-Service cards carry the add-service control.
        $booked = $this->bookAppointment($this->massage, AppointmentStatus::Booked);
        $inService = $this->bookAppointment($this->facial, AppointmentStatus::InService, $this->facialStaff);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('data-appointment-id="'.$booked->id.'"', $html);
        $this->assertStringContainsString('data-appointment-id="'.$inService->id.'"', $html);
        $this->assertStringContainsString('data-bs-target="#addedServiceModal"', $html);
    }

    public function test_payments_card_lists_main_and_added_services_together(): void
    {
        $appointment = $this->bookAppointment($this->massage, AppointmentStatus::InService);
        // Adding is allowed once the visit has started.
        $this->add($appointment->id, [
            'service_id' => $this->facial->id,
            'personnel_id' => $this->facialStaff->id,
            'down_payment' => 150,
        ])->assertSessionHas('success');

        $html = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all']))
            ->assertOk()
            ->getContent();

        // A single services list holds the main service AND the added one,
        // each with its price; the payment-time add carries the "Added" tag
        // and its recorded down payment.
        $this->assertStringContainsString('<span>Addon Massage Probe</span>', $html);
        $this->assertStringContainsString('<span class="text-muted text-nowrap">₱500.00</span>', $html);
        $this->assertStringContainsString('Addon Facial Probe', $html);
        $this->assertStringContainsString('badge text-bg-secondary ms-1">Added</span>', $html);
        $this->assertStringContainsString('Down payment: ₱150.00', $html);
        $this->assertStringContainsString('<span class="text-muted text-nowrap">₱300.00</span>', $html);
    }

    public function test_payments_card_shows_creation_avails_plain_and_payment_adds_tagged_with_dp(): void
    {
        // Walk-in: main Addon Massage Probe (₱500) + a creation-time avail
        // (Addon Home Probe, ₱200 — no linked position so no staff needed).
        $this->actingAs($this->manager)
            ->post(route('admin.appointments.walk-in'), [
                'walk_in_name' => 'Card Split Guest',
                'service_id' => $this->massage->id,
                'personnel_id' => $this->massageStaff->id,
                'appointment_date' => $this->date,
                'start_time' => '15:00',
                'extras' => [
                    ['service_id' => $this->home->id],
                ],
            ])->assertSessionHasNoErrors()->assertRedirect();

        $appointment = Appointment::where('walk_in_name', 'Card Split Guest')->latest('id')->firstOrFail();

        // A later payment-time add (with an explicit down payment).
        $this->add($appointment->id, [
            'service_id' => $this->facial->id,
            'personnel_id' => $this->facialStaff->id,
            'down_payment' => 150,
            'method' => 'cash',
        ])->assertSessionHas('success');

        $html = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all']))
            ->assertOk()
            ->getContent();

        // The creation avail is listed plainly: no "Added" tag and no
        // down-payment line.
        $this->assertStringContainsString('Addon Home Probe', $html);
        $this->assertStringNotContainsString('Down payment: ₱20.00', $html);

        // Only the payment-time add is tagged, and it shows its own DP (₱150).
        $this->assertSame(1, substr_count($html, 'ms-1">Added</span>'));
        $this->assertStringContainsString('Down payment: ₱150.00', $html);
    }

    public function test_walk_in_services_catalog_carries_real_prices_and_flags_packages(): void
    {
        // A Packages-category service so the flag is proven beyond the seeded
        // Head-Spa packages.
        $packagesCategory = $this->service('Addon Catalog Package', 'Packages', 1200);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.appointments'))
            ->assertOk()
            ->getContent();

        preg_match('/const servicesCatalog = (\[.*?\]);/s', $html, $matches);
        $this->assertNotEmpty($matches, 'servicesJson catalog not embedded in the appointments page');

        $catalog = json_decode($matches[1], true);
        $this->assertIsArray($catalog);
        $byName = collect($catalog)->keyBy('name')->all();

        // Real price present on a regular service (a missing key is what made
        // the extra rows render "₱NaN").
        $this->assertArrayHasKey('Signature Facial', $byName);
        $this->assertIsNumeric($byName['Signature Facial']['price']);
        $this->assertFalse($byName['Signature Facial']['is_package']);

        // The Head-Spa "Package …" services live in an ordinary category, so
        // the category test alone misses them — they must still be flagged as
        // packages so the avail-service builder never offers them.
        foreach (['Package EXA', 'Package DEE', 'Package SAM', 'Package LIZ', 'Perfect Package'] as $name) {
            $this->assertArrayHasKey($name, $byName, $name.' missing from the catalog');
            $this->assertTrue($byName[$name]['is_package'], $name.' must be flagged as a package');
        }

        // A Packages-category service is flagged too.
        $packagesName = $packagesCategory->name;
        $this->assertArrayHasKey($packagesName, $byName);
        $this->assertTrue($byName[$packagesName]['is_package']);
    }

    public function test_feed_reports_added_service_source_for_drawer_split(): void
    {
        // Creation-time avail → 'creation'.
        $this->actingAs($this->manager)
            ->post(route('admin.appointments.walk-in'), [
                'walk_in_name' => 'Feed Split Guest',
                'service_id' => $this->massage->id,
                'personnel_id' => $this->massageStaff->id,
                'appointment_date' => $this->date,
                'start_time' => '15:00',
                'extras' => [
                    ['service_id' => $this->facial->id, 'personnel_id' => $this->facialStaff->id],
                ],
            ])->assertSessionHasNoErrors()->assertRedirect();

        $walkIn = Appointment::where('walk_in_name', 'Feed Split Guest')->latest('id')->firstOrFail();

        // Later payment-time add → 'payment'.
        $booked = $this->bookAppointment($this->massage);
        $this->add($booked->id, ['service_id' => $this->facial->id])->assertSessionHas('success');

        $events = $this->actingAs($this->manager)
            ->getJson(route('admin.appointments.feed'))
            ->assertOk()
            ->json();

        $byId = collect($events)->keyBy('id');

        $this->assertSame(
            'creation',
            $byId[$walkIn->id]['extendedProps']['addedServices'][0]['source'] ?? null,
        );
        $this->assertSame(
            'payment',
            $byId[$booked->id]['extendedProps']['addedServices'][0]['source'] ?? null,
        );
    }

    public function test_add_with_down_payment_records_exact_amount_and_grows_balance(): void
    {
        $appointment = $this->bookAppointment($this->massage); // ₱500

        $this->add($appointment->id, [
            'service_id' => $this->facial->id, // ₱300
            'personnel_id' => $this->facialStaff->id,
            'down_payment' => 150,
            'method' => 'gcash',
        ])->assertRedirect()->assertSessionHas('success');

        // A Verified kind=branch down payment for exactly the entered amount.
        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointment->id,
            'kind' => 'branch',
            'amount' => 150.00,
            'method' => 'gcash',
            'status' => 'verified',
            'verified_by' => $this->manager->id,
        ]);

        // booked_price grew by the FULL added price; the balance keeps the
        // remainder (₱650 = ₱500 + ₱300 − ₱150).
        $appointment->refresh();
        $this->assertSame(800.0, (float) $appointment->booked_price);
        $this->assertSame(150.0, $appointment->verifiedPaid());
        $this->assertSame(650.0, $appointment->balanceDue());
    }

    public function test_add_without_down_payment_creates_no_payment(): void
    {
        $appointment = $this->bookAppointment($this->massage);

        $this->add($appointment->id, [
            'service_id' => $this->facial->id,
            'personnel_id' => $this->facialStaff->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(0, $appointment->payments()->count());
        $appointment->refresh();
        $this->assertSame(800.0, (float) $appointment->booked_price);
        $this->assertSame(800.0, $appointment->balanceDue());
    }

    public function test_add_with_zero_down_payment_creates_no_payment(): void
    {
        $appointment = $this->bookAppointment($this->massage);

        $this->add($appointment->id, [
            'service_id' => $this->facial->id,
            'personnel_id' => $this->facialStaff->id,
            'down_payment' => 0,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(0, $appointment->payments()->count());
        $appointment->refresh();
        $this->assertSame(800.0, (float) $appointment->booked_price);
        $this->assertSame(800.0, $appointment->balanceDue());
    }

    public function test_add_down_payment_accepts_two_decimals_and_is_capped_at_price(): void
    {
        $oddService = $this->service('Addon Round Probe', 'Facial', 399);
        $appointment = $this->bookAppointment($this->massage); // ₱500

        $this->add($appointment->id, [
            'service_id' => $oddService->id, // ₱399
            'personnel_id' => $this->facialStaff->id,
            'down_payment' => 199.50,
            'method' => 'gcash',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointment->id,
            'kind' => 'branch',
            'amount' => 199.50,
            'method' => 'gcash',
        ]);

        $appointment->refresh();
        $this->assertSame(899.0, (float) $appointment->booked_price);
        $this->assertSame(699.5, $appointment->balanceDue());

        // Above the added price -> recorded at the price, not over it.
        $appointmentTwo = $this->bookAppointment($this->massage, AppointmentStatus::Booked);
        $appointmentTwo->update(['start_time' => '11:00']);

        $this->add($appointmentTwo->id, [
            'service_id' => $this->facial->id,
            'personnel_id' => $this->facialStaff->id,
            'down_payment' => 999,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointmentTwo->id,
            'kind' => 'branch',
            'amount' => 300.00,
        ]);
    }
}
