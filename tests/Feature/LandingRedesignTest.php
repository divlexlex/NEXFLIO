<?php

namespace Tests\Feature;

use App\Models\Promo;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_services_page_deep_links_to_a_pre_selected_category(): void
    {
        $response = $this->get('/services?category=Head Spa');
        $response->assertOk();
        $response->assertViewHas('activeCategory', 'Head Spa');
    }

    public function test_services_page_falls_back_to_all_for_an_unknown_category(): void
    {
        $response = $this->get('/services?category=Not A Real Category');
        $response->assertOk();
        $response->assertViewHas('activeCategory', null);
        $response->assertSee('All Services');
    }

    public function test_client_dashboard_shows_four_quick_actions_and_special_for_you(): void
    {
        $client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $service = Service::first();

        $promo = Promo::create(['title' => 'Dashboard Promo Check', 'discount_type' => 'bundle', 'price' => 499, 'is_active' => true]);
        $promo->services()->sync([$service->id]);

        $response = $this->actingAs($client)->get('/account');
        $response->assertOk();
        $response->assertSee('Book Appointment');
        $response->assertSee('Browse Services');
        $response->assertSee('My Bookings');
        $response->assertSee('My Offers');
        $response->assertSee('Special for You');
        $response->assertSee('Dashboard Promo Check');
    }

    public function test_client_dashboard_hides_special_for_you_when_no_live_promos(): void
    {
        $client = User::where('email', 'client@nexflio.test')->firstOrFail();

        $response = $this->actingAs($client)->get('/account');
        $response->assertOk();
        $response->assertDontSee('Special for You');
    }
}
