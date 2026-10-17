<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data-driven service categories: seeded order, admin form + filter driven
 * by the table, the "+ New category…" endpoint, service validation against
 * the table, and the public Services page deriving pills/sections from it.
 */
class ServiceCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $owner;

    private User $manager;

    private User $client;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::where('email', 'admin@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
    }

    private function makeService(array $overrides = []): Service
    {
        return Service::create(array_merge([
            'name' => 'Category Test Service',
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
            'name' => 'Category Test New Service',
            'category' => 'Massage',
            'description' => 'Test',
            'price' => 250,
            'duration_minutes' => 60,
            'status' => 'active',
        ], $overrides);
    }

    public function test_service_categories_seeded_in_order(): void
    {
        $this->assertSame(
            ['Facial', 'Massage', 'Nails', 'Lashes & Brows', 'Aesthetics', 'Head Spa', 'Home Service', 'Packages'],
            ServiceCategory::orderBy('id')->pluck('name')->all()
        );
    }

    public function test_admin_form_and_filter_list_table_categories(): void
    {
        ServiceCategory::create(['name' => 'Zzz Custom Category']);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.services'))
            ->assertOk()
            ->getContent();

        // Appears in both the filter select and the service-form select.
        $this->assertSame(2, substr_count($html, '<option value="Zzz Custom Category"'));

        // The form select offers "+ New category…", the filter does not.
        $this->assertSame(1, substr_count($html, '<option value="__new__">+ New category…</option>'));
        $this->assertStringContainsString('id="newServiceCategoryModal"', $html);
        $this->assertStringContainsString('id="newServiceCategoryForm"', $html);
    }

    public function test_manager_and_owner_can_store_a_service_category(): void
    {
        $this->actingAs($this->manager)->postJson(
            route('admin.service-categories.store'),
            ['name' => 'Wellness']
        )->assertCreated()->assertJson(['name' => 'Wellness']);

        $this->actingAs($this->owner)->postJson(
            route('admin.service-categories.store'),
            ['name' => 'Aromatherapy']
        )->assertCreated();

        $this->assertDatabaseHas('service_categories', ['name' => 'Wellness']);
        $this->assertDatabaseHas('service_categories', ['name' => 'Aromatherapy']);
    }

    public function test_store_service_category_rejects_duplicates_case_insensitively(): void
    {
        $this->actingAs($this->manager)->postJson(
            route('admin.service-categories.store'),
            ['name' => 'Nails']
        )->assertStatus(422);

        $this->actingAs($this->manager)->postJson(
            route('admin.service-categories.store'),
            ['name' => 'nails']
        )->assertStatus(422);

        $this->assertSame(8, ServiceCategory::count());
    }

    public function test_store_service_category_forbidden_for_client_and_staff(): void
    {
        $this->actingAs($this->client)->postJson(
            route('admin.service-categories.store'),
            ['name' => 'Sneaky']
        )->assertForbidden();

        $this->actingAs($this->staff)->postJson(
            route('admin.service-categories.store'),
            ['name' => 'Sneaky']
        )->assertForbidden();

        $this->assertDatabaseMissing('service_categories', ['name' => 'Sneaky']);
    }

    public function test_store_service_category_plain_post_fallback_redirects_with_flash(): void
    {
        $this->actingAs($this->manager)->post(
            route('admin.service-categories.store'),
            ['name' => 'Body Care']
        )->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('service_categories', ['name' => 'Body Care']);
    }

    public function test_service_validation_uses_the_table(): void
    {
        ServiceCategory::create(['name' => 'Wellness']);

        // Store with a DB-created custom category passes.
        $this->actingAs($this->owner)->post(
            route('admin.services.store'),
            $this->servicePayload(['name' => 'Custom Category Service', 'category' => 'Wellness'])
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('services', ['name' => 'Custom Category Service', 'category' => 'Wellness']);

        // A category that is NOT in the table is rejected.
        $this->actingAs($this->owner)->post(
            route('admin.services.store'),
            $this->servicePayload(['name' => 'Bogus Category Service', 'category' => 'Totally Unknown'])
        )->assertSessionHasErrors('category');

        $this->assertDatabaseMissing('services', ['name' => 'Bogus Category Service']);

        // Update to another table category passes; to a non-table category fails.
        $service = $this->makeService(['name' => 'Updatable Service', 'category' => 'Wellness']);

        $this->actingAs($this->owner)->patch(
            route('admin.services.update', $service->id),
            $this->servicePayload(['name' => 'Updatable Service', 'category' => 'Nails'])
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Nails', $service->fresh()->category);

        $this->actingAs($this->owner)->patch(
            route('admin.services.update', $service->id),
            $this->servicePayload(['name' => 'Updatable Service', 'category' => 'Totally Unknown'])
        )->assertSessionHasErrors('category');

        $this->assertSame('Nails', $service->fresh()->category);
    }

    public function test_public_page_renders_custom_category_pill_and_section(): void
    {
        ServiceCategory::create(['name' => 'Wellness Oasis']);
        $this->makeService(['name' => 'Aroma Ritual', 'category' => 'Wellness Oasis']);

        $this->get('/services')
            ->assertOk()
            ->assertSee('Wellness Oasis')
            ->assertSee('Aroma Ritual');
    }

    public function test_empty_custom_category_does_not_appear_on_public_page(): void
    {
        ServiceCategory::create(['name' => 'Zzz Empty Category']);

        $this->get('/services')
            ->assertOk()
            ->assertDontSee('Zzz Empty Category');
    }

    public function test_unknown_service_category_is_still_shown_defensively(): void
    {
        // A service whose category is not in the table must never be hidden.
        $this->makeService(['name' => 'Orphan Category Service', 'category' => 'Legacy Orphan']);

        $this->get('/services')
            ->assertOk()
            ->assertSee('Legacy Orphan')
            ->assertSee('Orphan Category Service');
    }

    public function test_head_spa_packages_pill_behaviour_is_untouched(): void
    {
        $this->get('/services')
            ->assertOk()
            ->assertSee('data-bs-target="#cat-packages"', false)
            ->assertSee('See package tiers');
    }

    public function test_packages_category_collision_keeps_both_working(): void
    {
        // A service assigned to a DB category literally named "Packages"
        // must render in its own pill/section while the fixed Head Spa
        // "Packages" tab keeps presenting the package records.
        $this->makeService(['name' => 'Custom Packages Service', 'category' => 'Packages']);

        $html = $this->get('/services')->assertOk()->getContent();

        // Fixed tab (gift icon) + DB category pill (fallback tag icon), both labelled "Packages".
        $this->assertStringContainsString('bi-gift"></i>Packages', $html);
        $this->assertStringContainsString('bi-tag"></i>Packages', $html);
        $this->assertStringContainsString('data-bs-target="#cat-packages"', $html);
        $this->assertStringContainsString('Custom Packages Service', $html);
        $this->assertStringContainsString('Perfect Package', $html);
    }
}
