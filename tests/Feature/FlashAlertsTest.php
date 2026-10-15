<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * System-wide flash banners — one shared partial (partials.flash-alerts)
 * included exactly once per layout, so every flash message renders exactly
 * once per page and auto-dismisses after 4500 ms.
 */
class FlashAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_admin_flash_renders_exactly_once(): void
    {
        $manager = User::where('email', 'manager@nexflio.test')->firstOrFail();

        $html = $this->actingAs($manager)
            ->withSession(['success' => 'Flash Once Message Xyz'])
            ->get(route('admin.appointments'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Flash Once Message Xyz'));
        $this->assertStringContainsString('data-nx-flash', $html);
    }

    public function test_staff_flash_renders_exactly_once(): void
    {
        $staff = User::where('email', 'staff@nexflio.test')->firstOrFail();

        $html = $this->actingAs($staff)
            ->withSession(['success' => 'Flash Once Message Xyz'])
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Flash Once Message Xyz'));
    }

    public function test_public_flash_renders_exactly_once(): void
    {
        $client = User::where('email', 'client@nexflio.test')->firstOrFail();

        $html = $this->actingAs($client)
            ->withSession(['success' => 'Flash Once Message Xyz'])
            ->get(route('account.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Flash Once Message Xyz'));
    }

    public function test_auth_status_still_renders_only_in_its_card(): void
    {
        $html = $this->withSession(['status' => 'Auth Status Once Xyz'])
            ->get(route('login'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Auth Status Once Xyz'));
    }

    public function test_layouts_include_partial_once_with_autodismiss(): void
    {
        foreach (['admin', 'staff', 'public'] as $layout) {
            $source = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));
            $this->assertSame(1, substr_count($source, "@include('partials.flash-alerts')"), "{$layout} layout");
        }

        $partial = file_get_contents(resource_path('views/partials/flash-alerts.blade.php'));
        $this->assertStringContainsString('data-nx-flash', $partial);
        $this->assertStringContainsString('4500', $partial);
        $this->assertStringContainsString('[data-nx-flash]', $partial);
        $this->assertStringContainsString("el.style.opacity = '0'", $partial);
        $this->assertStringContainsString('el.remove()', $partial);
        $this->assertStringNotContainsString('bootstrap.Alert', $partial);
        $this->assertStringNotContainsString('typeof bootstrap', $partial);
    }

    public function test_real_flow_flash_renders_once_with_vanilla_dismiss_script(): void
    {
        $staff = User::where('email', 'staff@nexflio.test')->firstOrFail();

        $this->actingAs($staff)->post(route('staff.attendance.time-in'))->assertRedirect();

        $html = $this->actingAs($staff)->get(route('staff.dashboard'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Timed in — have a great shift!'));
        $this->assertSame(1, substr_count($html, '[data-nx-flash]'));
        $this->assertSame(1, substr_count($html, '4500'));
    }
}
