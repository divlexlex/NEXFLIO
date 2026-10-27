<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * (A) The Packages-category "package_details" field: admin validation +
 * persistence, and client rendering as an inclusions list.
 * (B) End-to-end branch/home bookings with a custom-labelled tier now use
 * that service's own configured down payment.
 */
class PackageDetailsFeeFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $owner;

    private User $manager;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::where('email', 'admin@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
    }

    private function servicePayload(array $overrides = []): array
    {
        // Packages require at least one catering position; Facial Technician
        // is seeded by PositionSeeder and is a safe default for store tests.
        $defaults = [
            'name' => 'Nx Package Service',
            'category' => 'Packages',
            'description' => 'Test',
            'price' => 500,
            'duration_minutes' => 60,
            'status' => 'active',
            'position_ids' => [\App\Models\Position::where('name', 'Facial Technician')->value('id')],
        ];

        return array_merge($defaults, $overrides);
    }

    private function makeService(array $overrides = []): Service
    {
        return Service::create(array_merge([
            'name' => 'Nx Service',
            'category' => 'Facial',
            'description' => 'Test',
            'price' => 100,
            'duration_minutes' => 30,
            'status' => 'active',
            'service_location_type' => 'branch',
        ], $overrides));
    }

    // ---- Part A: admin validation + persistence ---------------------------

    public function test_packages_category_service_requires_details(): void
    {
        $this->actingAs($this->owner)->post(
            route('admin.services.store'),
            $this->servicePayload(['name' => 'No Details Package', 'package_details' => ''])
        )->assertSessionHasErrors('package_details');

        $this->assertDatabaseMissing('services', ['name' => 'No Details Package']);

        // With details it persists exactly as typed.
        $typed = "Hot stone massage\n\n  Facial treatment  \nFoot spa";

        $this->actingAs($this->owner)->post(
            route('admin.services.store'),
            $this->servicePayload(['name' => 'With Details Package', 'package_details' => $typed])
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($typed, Service::where('name', 'With Details Package')->firstOrFail()->package_details);
    }

    public function test_non_package_service_details_are_optional(): void
    {
        $this->actingAs($this->owner)->post(
            route('admin.services.store'),
            $this->servicePayload(['name' => 'Regular Service', 'category' => 'Facial'])
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('services', ['name' => 'Regular Service', 'category' => 'Facial']);
        $this->assertNull(Service::where('name', 'Regular Service')->firstOrFail()->package_details);
    }

    public function test_admin_form_has_conditional_details_section(): void
    {
        $html = $this->actingAs($this->manager)->get(route('admin.services'))->assertOk()->getContent();

        // The section exists but starts hidden; the JS hook reveals it only
        // for the Packages category and the edit button carries the value.
        $this->assertStringContainsString('<div class="mb-2 d-none" id="svc-package-details-wrap">', $html);
        $this->assertStringContainsString('id="svc-package-details"', $html);
        $this->assertStringContainsString('id="svc-package-picker"', $html);
        $this->assertStringContainsString('id="svc-package-positions"', $html);
        $this->assertStringContainsString('Build from offered services', $html);
        $this->assertStringContainsString('Employee categories that will cater this package', $html);
        $this->assertStringContainsString('syncPackageDetails', $html);
        $this->assertStringContainsString("cat.value === 'Packages'", $html);
        $this->assertStringContainsString('data-package-details=', $html);
        $this->assertStringContainsString('data-position-ids=', $html);
    }

    public function test_admin_can_create_package_from_offered_service_names(): void
    {
        $facial = $this->makeService(['name' => 'Nx Facial Treatment', 'category' => 'Facial', 'price' => 499, 'duration_minutes' => 45]);
        $massage = $this->makeService(['name' => 'Nx Swedish Massage', 'category' => 'Massage', 'price' => 699, 'duration_minutes' => 60]);

        $details = $facial->name."\n".$massage->name;

        $this->actingAs($this->owner)->post(
            route('admin.services.store'),
            $this->servicePayload([
                'name' => 'Nx Built Package',
                'package_details' => $details,
                'price' => 1198,
                'duration_minutes' => 105,
            ])
        )->assertRedirect()->assertSessionHasNoErrors();

        $package = Service::where('name', 'Nx Built Package')->firstOrFail();
        $this->assertSame($details, $package->package_details);
        $this->assertSame('1198.00', (string) $package->price);
        $this->assertSame(105, $package->duration_minutes);
    }

    public function test_packages_require_at_least_one_employee_category(): void
    {
        $this->actingAs($this->owner)->post(
            route('admin.services.store'),
            $this->servicePayload([
                'name' => 'No Positions Package',
                'package_details' => 'Something included',
                'position_ids' => [],
            ])
        )->assertSessionHasErrors('position_ids');

        $this->assertDatabaseMissing('services', ['name' => 'No Positions Package']);
    }

    public function test_package_persists_multiple_employee_categories(): void
    {
        $facialId = \App\Models\Position::where('name', 'Facial Technician')->value('id');
        $massageId = \App\Models\Position::where('name', 'Massage Technician')->value('id');

        $this->actingAs($this->owner)->post(
            route('admin.services.store'),
            $this->servicePayload([
                'name' => 'Multi Position Package',
                'package_details' => "Facial\nMassage",
                'position_ids' => [$facialId, $massageId],
            ])
        )->assertRedirect()->assertSessionHasNoErrors();

        $package = Service::where('name', 'Multi Position Package')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['Facial Technician', 'Massage Technician'],
            $package->positions()->pluck('name')->all()
        );
    }

    public function test_public_services_page_renders_package_inclusions(): void
    {
        $package = $this->makeService([
            'name' => 'Nx Inclusions Package',
            'category' => 'Packages',
            'package_details' => "NxInclusion Alpha\nNxInclusion Beta",
        ]);
        // A non-Packages service with details set directly must NOT render them.
        $nonPackage = $this->makeService([
            'name' => 'Nx Non Package',
            'category' => 'Facial',
            'package_details' => 'NxHiddenInclusion',
        ]);

        // f6f3531 moved inclusions off the /services list cards and onto the
        // service detail page, so the grid must not carry them anymore.
        $listHtml = $this->get('/services')->assertOk()->getContent();
        $this->assertStringNotContainsString('NxInclusion Alpha', $listHtml);
        $this->assertStringNotContainsString('NxInclusion Beta', $listHtml);

        $detailHtml = $this->get(route('catalog.service', $package->id))->assertOk()->getContent();
        $this->assertStringContainsString('NxInclusion Alpha', $detailHtml);
        $this->assertStringContainsString('NxInclusion Beta', $detailHtml);

        // Non-Packages services keep their details out of the inclusions list.
        $nonPackageHtml = $this->get(route('catalog.service', $nonPackage->id))->assertOk()->getContent();
        $this->assertStringNotContainsString('NxHiddenInclusion', $nonPackageHtml);
    }

    public function test_booking_review_shows_package_inclusions(): void
    {
        $service = $this->makeService([
            'name' => 'Nx Review Package',
            'category' => 'Packages',
            'package_details' => "NxReview One\nNxReview Two",
        ]);

        $base = [
            'service' => $service->id,
            'date' => now()->addDay()->toDateString(),
            'time' => '10:00',
            'personnel' => 'any',
        ];

        $this->actingAs($this->client)
            ->get(route('account.booking.branch.review', $base))
            ->assertOk()
            ->assertSee('NxReview One')
            ->assertSee('NxReview Two');
    }

    // ---- Part B: custom-tier bookings use the configured DP ----------------

    public function test_branch_booking_with_custom_tier_label_completes(): void
    {
        Storage::fake('public');

        $service = $this->makeService([
            'name' => 'Nx Branch Custom Package',
            'category' => 'Packages',
            'package_details' => 'Inclusion one',
            'price' => 450,
            'down_payment' => 225,
            'service_location_type' => 'branch',
        ]);
        $variant = $service->variants()->create(['label' => 'Deluxe', 'price' => 450, 'sort' => 1]);

        $this->actingAs($this->client)->post(route('account.booking.branch.store'), [
            'service_id' => $service->id,
            'variant_id' => $variant->id,
            'personnel_id' => 'any',
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $appointment = \App\Models\Appointment::latest('id')->firstOrFail();
        $this->assertSame('Deluxe', $appointment->variant_label);
        $this->assertEquals(450, (float) $appointment->booked_price);
        $this->assertEquals(225, (float) $appointment->payment->amount);
    }

    public function test_home_booking_with_custom_tier_label_completes(): void
    {
        Storage::fake('public');

        $service = $this->makeService([
            'name' => 'Nx Home Custom Package',
            'category' => 'Packages',
            'package_details' => 'Inclusion one',
            'price' => 900,
            'down_payment' => 300,
            'service_location_type' => 'home',
        ]);
        $variant = $service->variants()->create(['label' => 'Deluxe', 'price' => 900, 'sort' => 1]);

        $this->actingAs($this->client)->post(route('account.booking.home.store'), [
            'service_id' => $service->id,
            'variant_id' => $variant->id,
            'personnel_id' => 'any',
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            'street_address' => '123 Test St',
            'barangay' => 'Barangay 1',
            'city_municipality' => 'Test City',
            'province' => 'Test Province',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $appointment = \App\Models\Appointment::latest('id')->firstOrFail();
        $this->assertSame('Deluxe', $appointment->variant_label);
        $this->assertEquals(900, (float) $appointment->booked_price);
        $this->assertEquals(300, (float) $appointment->payment->amount);
    }

    public function test_custom_tier_label_cash_allowed_when_no_down_payment(): void
    {
        Storage::fake('public');

        $service = $this->makeService([
            'name' => 'Nx Cash Custom Package',
            'category' => 'Packages',
            'price' => 450,
            'down_payment' => null,
            'service_location_type' => 'branch',
        ]);
        $variant = $service->variants()->create(['label' => 'Deluxe', 'price' => 450, 'sort' => 1]);

        $this->actingAs($this->client)->post(route('account.booking.branch.store'), [
            'service_id' => $service->id,
            'variant_id' => $variant->id,
            'personnel_id' => 'any',
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'method' => 'cash',
            'payment_proof' => null,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $appointment = \App\Models\Appointment::latest('id')->firstOrFail();
        $this->assertSame('cash', $appointment->payment->method);
        $this->assertEquals(450, (float) $appointment->payment->amount);
    }
}
