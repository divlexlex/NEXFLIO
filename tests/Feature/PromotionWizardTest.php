<?php

namespace Tests\Feature;

use App\Models\Promo;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionWizardTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $owner;

    private Service $serviceA;

    private Service $serviceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::where('email', 'admin@nexflio.test')->firstOrFail();

        $this->serviceA = Service::create([
            'name' => 'Test Facial', 'category' => 'Facial', 'price' => 1000,
            'duration_minutes' => 60, 'status' => 'active',
        ]);
        $this->serviceB = Service::create([
            'name' => 'Test Massage', 'category' => 'Massage', 'price' => 500,
            'duration_minutes' => 45, 'status' => 'active',
        ]);
    }

    public function test_create_wizard_page_renders_with_services(): void
    {
        $response = $this->actingAs($this->owner)->get('/admin/promos/create');

        $response->assertOk();
        $response->assertSee('Basic Info');
        $response->assertSee('Select Services');
        $response->assertSee('Schedule & Visibility');
        $response->assertSee('Review & Publish');
        $response->assertSee('Test Facial');
        $response->assertDontSee('Mobile App');
    }

    public function test_percentage_promo_applies_correctly_to_linked_services(): void
    {
        $response = $this->actingAs($this->owner)->post('/admin/promos', [
            'title' => '20% Off Facials',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'service_ids' => [$this->serviceA->id],
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.promos'));
        $promo = Promo::where('title', '20% Off Facials')->firstOrFail();

        $this->assertSame('percentage', $promo->discount_type);
        $this->assertNull($promo->price);
        $this->assertSame('20% OFF', $promo->discountLabel());
        $this->assertEqualsWithDelta(800.0, $promo->effectivePriceFor($this->serviceA), 0.01);
        $this->assertTrue($promo->services->contains($this->serviceA));
    }

    public function test_fixed_amount_promo_applies_correctly(): void
    {
        $this->actingAs($this->owner)->post('/admin/promos', [
            'title' => '₱100 Off Massage',
            'discount_type' => 'fixed_amount',
            'discount_value' => 100,
            'service_ids' => [$this->serviceB->id],
            'is_active' => '1',
        ]);

        $promo = Promo::where('title', '₱100 Off Massage')->firstOrFail();
        $this->assertSame('₱100 OFF', $promo->discountLabel());
        $this->assertEqualsWithDelta(400.0, $promo->effectivePriceFor($this->serviceB), 0.01);
    }

    public function test_bundle_promo_uses_flat_price_and_multiple_services(): void
    {
        $this->actingAs($this->owner)->post('/admin/promos', [
            'title' => 'Combo Deal',
            'discount_type' => 'bundle',
            'price' => 1200,
            'service_ids' => [$this->serviceA->id, $this->serviceB->id],
            'is_active' => '1',
        ]);

        $promo = Promo::where('title', 'Combo Deal')->firstOrFail();
        $this->assertNull($promo->discount_value);
        $this->assertEquals(1200, $promo->price);
        $this->assertNull($promo->discountLabel());
        $this->assertCount(2, $promo->services);
    }

    public function test_percentage_over_100_is_rejected(): void
    {
        $response = $this->actingAs($this->owner)->post('/admin/promos', [
            'title' => 'Bad Promo',
            'discount_type' => 'percentage',
            'discount_value' => 150,
            'service_ids' => [$this->serviceA->id],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('promos', ['title' => 'Bad Promo']);
    }

    public function test_discount_promo_requires_at_least_one_service(): void
    {
        $response = $this->actingAs($this->owner)->from('/admin/promos/create')->post('/admin/promos', [
            'title' => 'No Service Promo',
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ]);

        $response->assertSessionHasErrors('service_ids');
        $this->assertDatabaseMissing('promos', ['title' => 'No Service Promo']);
    }

    public function test_edit_page_preselects_linked_services(): void
    {
        $promo = Promo::create(['title' => 'Existing Promo', 'discount_type' => 'bundle', 'price' => 500, 'is_active' => true]);
        $promo->services()->sync([$this->serviceA->id]);

        $response = $this->actingAs($this->owner)->get("/admin/promos/{$promo->id}/edit");
        $response->assertOk();
        $response->assertSee('checked', false);
        $response->assertSee('Test Facial');
    }

    public function test_update_resyncs_services(): void
    {
        $promo = Promo::create(['title' => 'Resync Promo', 'discount_type' => 'bundle', 'price' => 500, 'is_active' => true]);
        $promo->services()->sync([$this->serviceA->id]);

        $this->actingAs($this->owner)->patch("/admin/promos/{$promo->id}", [
            'title' => 'Resync Promo',
            'discount_type' => 'bundle',
            'price' => 500,
            'service_ids' => [$this->serviceB->id],
            'is_active' => '1',
        ])->assertRedirect(route('admin.promos'));

        $promo->refresh();
        $this->assertFalse($promo->services->contains($this->serviceA));
        $this->assertTrue($promo->services->contains($this->serviceB));
    }

    public function test_index_groups_promos_by_computed_status(): void
    {
        Promo::create(['title' => 'Active One', 'discount_type' => 'bundle', 'price' => 100, 'is_active' => true]);
        Promo::create(['title' => 'Scheduled One', 'discount_type' => 'bundle', 'price' => 100, 'is_active' => true, 'starts_at' => now()->addWeek()]);
        Promo::create(['title' => 'Expired One', 'discount_type' => 'bundle', 'price' => 100, 'is_active' => true, 'ends_at' => now()->subDay()]);
        Promo::create(['title' => 'Inactive One', 'discount_type' => 'bundle', 'price' => 100, 'is_active' => false]);

        $response = $this->actingAs($this->owner)->get('/admin/promos');
        $response->assertOk();
        $response->assertSee('Active One');
        $response->assertSee('Scheduled One');
        $response->assertSee('Expired One');
        $response->assertSee('Inactive One');
    }

    public function test_scheduled_and_expired_promos_are_hidden_from_public(): void
    {
        $scheduled = Promo::create(['title' => 'Future Promo', 'discount_type' => 'bundle', 'price' => 100, 'is_active' => true, 'starts_at' => now()->addWeek()]);
        $expired = Promo::create(['title' => 'Past Promo', 'discount_type' => 'bundle', 'price' => 100, 'is_active' => true, 'ends_at' => now()->subDay()]);
        $live = Promo::create(['title' => 'Live Promo', 'discount_type' => 'bundle', 'price' => 100, 'is_active' => true]);

        $this->assertFalse(Promo::live()->pluck('id')->contains($scheduled->id));
        $this->assertFalse(Promo::live()->pluck('id')->contains($expired->id));
        $this->assertTrue(Promo::live()->pluck('id')->contains($live->id));

        $this->get('/offers')->assertOk()->assertDontSee('Future Promo')->assertDontSee('Past Promo')->assertSee('Live Promo');
    }

    public function test_offers_page_shows_discount_ribbon_and_struck_price(): void
    {
        $promo = Promo::create(['title' => 'Ribbon Promo', 'discount_type' => 'percentage', 'discount_value' => 25, 'is_active' => true]);
        $promo->services()->sync([$this->serviceA->id]);

        $response = $this->get('/offers');
        $response->assertOk();
        $response->assertSee('25% OFF');
        $response->assertSee(number_format($this->serviceA->price, 2));
    }
}
