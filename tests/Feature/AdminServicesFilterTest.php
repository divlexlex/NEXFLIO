<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin Services list filters (q / category / status) and the canonical
 * category dropdown validation on create.
 */
class AdminServicesFilterTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::where('email', 'admin@nexflio.test')->firstOrFail();
    }

    private function makeService(array $overrides = []): Service
    {
        return Service::create(array_merge([
            'name' => 'Test Service',
            'category' => 'Facial',
            'description' => 'Test',
            'price' => 100,
            'duration_minutes' => 30,
            'status' => 'active',
            'service_location_type' => 'branch',
        ], $overrides));
    }

    private function servicePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Filter Test New Service',
            'category' => 'Massage',
            'description' => 'Test',
            'price' => 250,
            'duration_minutes' => 60,
            'status' => 'active',
        ], $overrides);
    }

    public function test_filtering_by_category_returns_only_that_category(): void
    {
        $this->makeService(['name' => 'FilterCat Facial One', 'category' => 'Facial']);
        $this->makeService(['name' => 'FilterCat Massage One', 'category' => 'Massage']);

        $html = $this->actingAs($this->owner)
            ->get(route('admin.services', ['category' => 'Facial']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<strong>FilterCat Facial One</strong>', $html);
        $this->assertStringNotContainsString('<strong>FilterCat Massage One</strong>', $html);

        $expected = Service::where('category', 'Facial')->count();
        $this->assertStringContainsString("Showing {$expected} of ".Service::count().' services', $html);
    }

    public function test_q_matches_by_partial_name(): void
    {
        $this->makeService(['name' => 'ZzzQuirkyName Service']);
        $this->makeService(['name' => 'Ordinary Other Service']);

        $html = $this->actingAs($this->owner)
            ->get(route('admin.services', ['q' => 'QuirkyName']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<strong>ZzzQuirkyName Service</strong>', $html);
        $this->assertStringNotContainsString('<strong>Ordinary Other Service</strong>', $html);
    }

    public function test_status_filter_works(): void
    {
        $this->makeService(['name' => 'FilterStatus Active One', 'status' => 'active']);
        $this->makeService(['name' => 'FilterStatus Inactive One', 'status' => 'inactive']);

        $html = $this->actingAs($this->owner)
            ->get(route('admin.services', ['status' => 'inactive']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<strong>FilterStatus Inactive One</strong>', $html);
        $this->assertStringNotContainsString('<strong>FilterStatus Active One</strong>', $html);
    }

    public function test_empty_filter_result_shows_match_message(): void
    {
        $html = $this->actingAs($this->owner)
            ->get(route('admin.services', ['q' => 'NoSuchServiceNameAnywhere']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('No services match the filters.', $html);
    }

    public function test_creating_with_category_outside_allowed_list_fails(): void
    {
        $this->actingAs($this->owner)
            ->post(route('admin.services.store'), $this->servicePayload(['category' => 'Made Up Category']))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseMissing('services', ['name' => 'Filter Test New Service']);
    }

    public function test_creating_with_valid_category_succeeds(): void
    {
        $this->actingAs($this->owner)
            ->post(route('admin.services.store'), $this->servicePayload(['category' => 'Nails']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('services', ['name' => 'Filter Test New Service', 'category' => 'Nails']);
    }

    public function test_updating_legacy_category_keeps_it_valid(): void
    {
        $service = $this->makeService(['name' => 'Legacy Cat Service', 'category' => 'Legacy Category']);

        $this->actingAs($this->owner)
            ->patch(route('admin.services.update', $service->id), $this->servicePayload([
                'name' => 'Legacy Cat Service',
                'category' => 'Legacy Category',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Legacy Category', $service->fresh()->category);
    }
}
