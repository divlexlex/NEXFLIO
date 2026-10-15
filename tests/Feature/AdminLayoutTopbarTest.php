<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin layout: account/theme/logout live in the top header bar (nx-topbar),
 * not the sidebar; the theme toggle keeps its ids so the bottom script works.
 */
class AdminLayoutTopbarTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_manager_dashboard_has_topbar_and_clean_sidebar(): void
    {
        $manager = User::where('email', 'manager@nexflio.test')->firstOrFail();

        $html = $this->actingAs($manager)->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('nx-topbar', $html);
        $this->assertSame(1, substr_count($html, 'id="themeToggle"'));

        $sidebar = Str::before($html, '<main');
        $this->assertStringNotContainsString('Log out', $sidebar);
        $this->assertStringNotContainsString(route('logout'), $sidebar);

        $main = Str::after($html, '<main');
        $this->assertStringContainsString('Log out', $main);
        $this->assertStringContainsString(route('logout'), $main);
        $this->assertStringContainsString($manager->email, $main);
    }

    public function test_owner_master_schedule_has_topbar(): void
    {
        $owner = User::where('email', 'admin@nexflio.test')->firstOrFail();

        $html = $this->actingAs($owner)->get(route('admin.master-schedule'))->assertOk()->getContent();

        $this->assertStringContainsString('nx-topbar', $html);
        $this->assertSame(1, substr_count($html, 'id="themeToggle"'));

        $sidebar = Str::before($html, '<main');
        $this->assertStringNotContainsString('Log out', $sidebar);
    }
}
