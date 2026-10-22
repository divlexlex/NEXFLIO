<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Flio — the admin floating chat assistant.
 *
 * Everything here uses Http::fake: no real network, no real API key. The
 * payload-discipline test proves the snapshot carries aggregates (service,
 * staff and item names are allowed) but never client PII.
 */
class FlioAdminChatTest extends TestCase
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

    private function fakeGeminiSuccess(string $text = 'Revenue is up this week.'): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => $text]]]],
                ],
            ]),
        ]);
    }

    // ===== Access control =====

    public function test_guests_cannot_use_the_endpoint(): void
    {
        Http::fake();

        $this->post(route('admin.flio.ask'), ['question' => 'hi'])
            ->assertRedirect(route('login'));

        // JSON request is denied the same way (guest is redirected to login).
        $this->postJson(route('admin.flio.ask'), ['question' => 'hi'])
            ->assertRedirect(route('login'));

        Http::assertNothingSent();
    }

    public function test_client_and_staff_cannot_use_the_endpoint(): void
    {
        Http::fake();

        $this->actingAs($this->client)
            ->postJson(route('admin.flio.ask'), ['question' => 'hi'])
            ->assertForbidden();

        $this->actingAs($this->staff)
            ->postJson(route('admin.flio.ask'), ['question' => 'hi'])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_owner_and_manager_can_use_the_endpoint(): void
    {
        config(['services.gemini.key' => 'test-key']);
        $this->fakeGeminiSuccess('Revenue for this week is ₱0.');

        $this->actingAs($this->owner)
            ->postJson(route('admin.flio.ask'), ['question' => 'How is revenue this week?'])
            ->assertOk()
            ->assertJson(['answer' => 'Revenue for this week is ₱0.', 'configured' => true]);

        $this->actingAs($this->manager)
            ->postJson(route('admin.flio.ask'), ['question' => 'How is revenue this week?'])
            ->assertOk()
            ->assertJson(['configured' => true]);
    }

    // ===== Happy path / degradation =====

    public function test_happy_path_returns_the_fake_answer(): void
    {
        config(['services.gemini.key' => 'test-key']);
        $this->fakeGeminiSuccess('This week revenue is ₱12,500 and Fridays are busiest.');

        $this->actingAs($this->manager)
            ->postJson(route('admin.flio.ask'), ['question' => 'How is revenue this week?'])
            ->assertOk()
            ->assertJson([
                'answer' => 'This week revenue is ₱12,500 and Fridays are busiest.',
                'configured' => true,
            ]);
    }

    public function test_unconfigured_key_reports_not_configured(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake();

        $this->actingAs($this->manager)
            ->postJson(route('admin.flio.ask'), ['question' => 'How is revenue this week?'])
            ->assertOk()
            ->assertJson(['answer' => null, 'configured' => false]);

        Http::assertNothingSent();
    }

    public function test_gemini_500_returns_null_answer_with_http_200(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $this->actingAs($this->manager)
            ->postJson(route('admin.flio.ask'), ['question' => 'How is revenue this week?'])
            ->assertOk()
            ->assertJson(['answer' => null, 'configured' => true]);
    }

    // ===== Payload discipline =====

    public function test_payload_carries_aggregates_but_no_client_pii(): void
    {
        config(['services.gemini.key' => 'test-key']);
        $this->fakeGeminiSuccess();

        $service = Service::create([
            'name' => 'Flio Test Massage',
            'category' => 'Massage',
            'price' => 900.00,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);

        // Distinctive client PII that must never leave the server.
        $secretClient = User::create([
            'name' => 'Zxqv Secretboss',
            'email' => 'zxqv.secretboss@example.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_CLIENT,
        ]);

        $appointment = Appointment::create([
            'user_id' => $secretClient->id,
            'walk_in_phone' => '09998887777',
            'service_id' => $service->id,
            'booked_price' => 900.00,
            'appointment_date' => now()->subDays(2)->toDateTimeString(),
            'start_time' => '10:00',
            'status' => AppointmentStatus::Completed,
        ]);

        Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => 900.00,
            'method' => 'gcash',
            'status' => PaymentStatus::Verified,
            'verified_at' => now()->subDay()->toDateTimeString(),
        ]);

        // A low-stock item whose name is an allowed aggregate.
        Inventory::create([
            'item_name' => 'Zz Flio Low Stock',
            'category' => 'Nails',
            'unit' => 'pc',
            'quantity' => 0,
            'reorder_point' => 3,
            'price_per_unit' => 0,
        ]);

        $this->actingAs($this->manager)
            ->postJson(route('admin.flio.ask'), ['question' => 'What should I know today?'])
            ->assertOk();

        $recorded = Http::recorded();
        $this->assertNotEmpty($recorded, 'The endpoint must have called Gemini.');
        $body = $recorded[0][0]->body();

        foreach (['Zxqv Secretboss', 'zxqv.secretboss@example.test', '09998887777'] as $pii) {
            $this->assertStringNotContainsString($pii, $body, "Client PII [{$pii}] leaked into the Flio payload.");
        }

        // Aggregates — including management-visible names — are present.
        $this->assertStringContainsString('Dela Cruz Sheila', $body, 'Staff workload name should be in the payload.');
        $this->assertStringContainsString('Zz Flio Low Stock', $body, 'Low-stock item name should be in the payload.');
        $this->assertStringContainsString('revenue_this_week', $body);
        $this->assertStringContainsString('staff_workload_this_week', $body);
        $this->assertStringContainsString('low_stock', $body);
        $this->assertStringContainsString('active_promos', $body);
    }

    // ===== Validation =====

    public function test_question_is_required_and_validated(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake();

        $this->actingAs($this->manager)
            ->postJson(route('admin.flio.ask'), [])
            ->assertStatus(422);

        $this->actingAs($this->manager)
            ->postJson(route('admin.flio.ask'), ['question' => ''])
            ->assertStatus(422);

        $this->actingAs($this->manager)
            ->postJson(route('admin.flio.ask'), ['question' => str_repeat('a', 501)])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    // ===== History cap =====

    public function test_only_the_last_six_history_turns_are_sent(): void
    {
        config(['services.gemini.key' => 'test-key']);
        $this->fakeGeminiSuccess();

        $history = [];
        for ($i = 1; $i <= 12; $i++) {
            $history[] = ['role' => $i % 2 === 1 ? 'user' : 'assistant', 'text' => 'flio-h-'.$i];
        }

        $this->actingAs($this->manager)
            ->postJson(route('admin.flio.ask'), [
                'question' => 'How are we doing?',
                'history' => $history,
            ])
            ->assertOk();

        $contents = Http::recorded()[0][0]->data()['contents'];
        $texts = array_map(fn ($turn) => $turn['parts'][0]['text'], $contents);

        // Last 6 turns + the current question.
        $this->assertCount(7, $texts);
        $this->assertSame('flio-h-7', $texts[0]);
        $this->assertSame('flio-h-12', $texts[5]);
        $this->assertSame('How are we doing?', $texts[6]);
        $this->assertNotContains('flio-h-1', $texts);
        $this->assertNotContains('flio-h-6', $texts);
    }

    // ===== Widget rendering =====

    public function test_widget_renders_on_admin_pages_for_manager_and_owner(): void
    {
        $managerHtml = $this->actingAs($this->manager)->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('data-flio-widget', $managerHtml);
        $this->assertStringContainsString('flio-launcher', $managerHtml);
        $this->assertStringContainsString('id="flio-panel"', $managerHtml);
        $this->assertStringContainsString('data-flio-question', $managerHtml);
        $this->assertStringContainsString('Revenue this week?', $managerHtml);

        $ownerHtml = $this->actingAs($this->owner)->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('data-flio-widget', $ownerHtml);
    }

    public function test_widget_is_absent_from_the_public_site(): void
    {
        $html = $this->get(route('landing'))->assertOk()->getContent();

        $this->assertStringNotContainsString('flio-launcher', $html);
        $this->assertStringNotContainsString('data-flio-widget', $html);
        $this->assertStringNotContainsString('id="flio-panel"', $html);
    }

    public function test_manager_prompt_includes_the_business_only_restriction(): void
    {
        config(['services.gemini.key' => 'test-key']);
        $this->fakeGeminiSuccess();

        $this->actingAs($this->manager)
            ->postJson(route('admin.flio.ask'), ['question' => 'What is the best recipe for adobo?'])
            ->assertOk();

        Http::assertSent(function ($request) {
            $system = $request->data()['systemInstruction']['parts'][0]['text'] ?? '';

            $this->assertStringContainsString(
                "I can only answer questions about this business's operations.",
                $system
            );

            return true;
        });
    }
}
