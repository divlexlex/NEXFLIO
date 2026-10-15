<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Topbar clock + staff topbar: the admin topbar shows a live 12-hour clock
 * instead of a duplicate page title, and the staff portal has the same
 * topbar treatment (clock, user dropdown, single theme toggle, clean sidebar).
 */
class LayoutClockAndStaffTopbarTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_admin_topbar_contains_the_live_clock(): void
    {
        $manager = User::where('email', 'manager@nexflio.test')->firstOrFail();

        $html = $this->actingAs($manager)->get(route('admin.dashboard'))->assertOk()->getContent();

        $topbar = Str::between($html, '<header class="nx-topbar', '</header>');
        $this->assertStringContainsString('nxClock', $topbar);
    }

    public function test_staff_topbar_has_clock_single_toggle_and_clean_sidebar(): void
    {
        $staff = User::where('email', 'staff@nexflio.test')->firstOrFail();

        $html = $this->actingAs($staff)->get(route('staff.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('nx-topbar', $html);

        $topbar = Str::between($html, '<header class="nx-topbar', '</header>');
        $this->assertStringContainsString('nxClock', $topbar);

        $this->assertSame(1, substr_count($html, 'id="themeToggle"'));

        $sidebar = Str::before($html, '<main');
        $this->assertStringNotContainsString('Log out', $sidebar);
        $this->assertStringNotContainsString('Change Password', $sidebar);
    }
}
