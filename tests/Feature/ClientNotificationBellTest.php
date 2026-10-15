<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Navbar notifications bell (Design C, chunk 1): signed-in clients get the
 * bell with an unread badge and a dropdown of their latest notifications;
 * guests and non-clients get nothing; the mark-read endpoints keep working.
 */
class ClientNotificationBellTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
    }

    private function makeNotification(User $user, string $title, bool $read = false): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => 'Body for '.$title,
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_client_sees_bell_with_correct_unread_count(): void
    {
        $this->makeNotification($this->client, 'Bell First Unread');
        $this->makeNotification($this->client, 'Bell Second Unread');
        $this->makeNotification($this->client, 'Bell Already Read', read: true);

        $html = $this->actingAs($this->client)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="notificationBell"', $html);
        $this->assertStringContainsString(
            '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 10px;">2</span>',
            $html
        );
    }

    public function test_guest_does_not_see_bell(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('bi-bell', $html);
        $this->assertStringNotContainsString('notificationBell', $html);
    }

    public function test_non_client_does_not_see_bell(): void
    {
        $manager = User::where('email', 'manager@nexflio.test')->firstOrFail();

        $html = $this->actingAs($manager)->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('notificationBell', $html);
    }

    public function test_dropdown_lists_only_the_clients_own_notifications(): void
    {
        $this->makeNotification($this->client, 'Bell Mine One');

        $other = User::create([
            'name' => 'Other Client',
            'email' => 'other.client@nexflio.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_CLIENT,
        ]);
        $this->makeNotification($other, 'Bell Not Mine');

        $html = $this->actingAs($this->client)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Bell Mine One', $html);
        $this->assertStringNotContainsString('Bell Not Mine', $html);
    }

    public function test_empty_dropdown_shows_caught_up_copy(): void
    {
        $html = $this->actingAs($this->client)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString("You're all caught up — no notifications yet.", $html);
    }

    public function test_mark_read_endpoint_still_works(): void
    {
        $notification = $this->makeNotification($this->client, 'Bell To Read');

        $this->actingAs($this->client)
            ->patch(route('account.notifications.read', $notification->id))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_endpoint_still_works(): void
    {
        $this->makeNotification($this->client, 'Bell All One');
        $this->makeNotification($this->client, 'Bell All Two');

        $this->actingAs($this->client)
            ->patch(route('account.notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, Notification::where('user_id', $this->client->id)->whereNull('read_at')->count());
    }

    public function test_dropdown_menu_has_scroll_container_with_all_six_items(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->makeNotification($this->client, 'Bell Scroll Item '.$i);
        }

        $html = $this->actingAs($this->client)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('nx-notif-menu', $html);
        $this->assertStringContainsString('nx-notif-scroll', $html);

        // All six load (items 5-6 reached by scrolling, not paginated).
        for ($i = 1; $i <= 6; $i++) {
            $this->assertStringContainsString('Bell Scroll Item '.$i, $html);
        }

        // Header stays outside the scroll container.
        $this->assertTrue(
            strpos($html, 'Notifications</span>') < strpos($html, 'nx-notif-scroll')
        );
    }

    public function test_polish_css_rules_exist(): void
    {
        $css = file_get_contents(public_path('css/website.css'));

        $this->assertStringContainsString('#notificationBell:focus-visible', $css);
        $this->assertStringContainsString('rgba(198, 161, 91, .45)', $css);
        $this->assertStringContainsString('::selection', $css);
        $this->assertStringContainsString('.nx-notif-menu.show', $css);
        $this->assertStringContainsString('translateY(6px)', $css);
        $this->assertStringContainsString('.nx-notif-scroll', $css);
        $this->assertStringContainsString('scrollbar-width: thin', $css);
    }
}
