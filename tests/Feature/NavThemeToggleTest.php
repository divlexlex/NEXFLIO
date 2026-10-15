<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public nav dark-mode toggle placement: signed-in clients get it as an
 * item inside their account dropdown; guests and authenticated non-clients
 * keep the standalone navbar button. Exactly one #themeToggle per state —
 * the theme script in layouts.public binds by that id and is untouched.
 */
class NavThemeToggleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_client_toggle_lives_inside_account_dropdown_only(): void
    {
        $client = User::where('email', 'client@nexflio.test')->firstOrFail();

        $html = $this->actingAs($client)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('class="dropdown-item" id="themeToggle"', $html);
        $this->assertStringNotContainsString('id="themeToggle" class="btn btn-link', $html);
        $this->assertSame(1, substr_count($html, 'id="themeToggle"'));
    }

    public function test_guest_keeps_standalone_toggle_and_no_dropdown(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="themeToggle" class="btn btn-link', $html);
        $this->assertStringNotContainsString('class="dropdown-item" id="themeToggle"', $html);
        $this->assertStringNotContainsString('dropdown-menu', $html);
        $this->assertSame(1, substr_count($html, 'id="themeToggle"'));
    }

    public function test_non_client_auth_user_keeps_standalone_toggle(): void
    {
        $manager = User::where('email', 'manager@nexflio.test')->firstOrFail();

        $html = $this->actingAs($manager)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="themeToggle" class="btn btn-link', $html);
        $this->assertStringNotContainsString('class="dropdown-item" id="themeToggle"', $html);
        $this->assertSame(1, substr_count($html, 'id="themeToggle"'));
    }
}
