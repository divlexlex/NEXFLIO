<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Manual refresh button (icon only, no text label) on the admin/manager/
 * staff pages: the button + the #nx-refresh-region marker must be present
 * for the roles that may use each page, and the pages must refuse everyone
 * else. The swap itself is client-side JS (public/js/nx-refresh.js) and is
 * covered by the manual click checklist, not by PHPUnit — these tests guard
 * the server-side contract the script relies on: an authorized GET of the
 * page's own URL returns 200 with the region inside.
 */
class RefreshButtonTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $owner;

    private User $manager;

    private User $staff;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::where('email', 'admin@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
    }

    #[DataProvider('adminPages')]
    
    public function test_admin_pages_show_refresh_button_and_region(string $route): void
    {
        foreach ([$this->owner, $this->manager] as $user) {
            $html = $this->actingAs($user)->get(route($route))->assertOk()->getContent();
            $this->assertStringContainsString('id="nx-refresh-region"', $html);
            $this->assertStringContainsString('data-nx-refresh-btn', $html);
        }
    }

    public static function adminPages(): array
    {
        return [
            'dashboard' => ['admin.dashboard'],
            'appointments' => ['admin.appointments'],
            'payments' => ['admin.payments'],
            'change requests' => ['admin.appointment-requests'],
        ];
    }

    public function test_staff_dashboard_shows_refresh_button_and_region(): void
    {
        $html = $this->actingAs($this->staff)->get(route('staff.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('id="nx-refresh-region"', $html);
        $this->assertStringContainsString('data-nx-refresh-btn', $html);
    }

    public function test_refresh_button_renders_in_topbar_on_pages_without_a_region(): void
    {
        // The button lives in the layout topbar, so its markup is present
        // on every admin page; the script hides it client-side where the
        // page has no #nx-refresh-region (Employees has none).
        $html = $this->actingAs($this->owner)->get(route('admin.employees'))->assertOk()->getContent();
        $this->assertStringContainsString('data-nx-refresh-btn', $html);
        $this->assertStringNotContainsString('id="nx-refresh-region"', $html);
    }

    public function test_staff_cannot_open_admin_pages(): void
    {
        foreach (['admin.dashboard', 'admin.appointments', 'admin.payments', 'admin.appointment-requests'] as $route) {
            $this->actingAs($this->staff)->get(route($route))->assertForbidden();
        }
    }

    public function test_client_cannot_open_admin_or_staff_pages(): void
    {
        foreach (['admin.dashboard', 'admin.appointments', 'admin.payments', 'admin.appointment-requests', 'staff.dashboard'] as $route) {
            $this->actingAs($this->client)->get(route($route))->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect();
        $this->get(route('staff.dashboard'))->assertRedirect();
    }
}
