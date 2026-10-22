<?php

namespace Tests\Feature;

use App\Models\BusinessHours;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Public client chat — guest access, degradation, business-only knowledge,
 * PII exclusion, and history discipline.
 */
class ClientChatTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function fakeGeminiSuccess(string $text = 'Our lavender ritual is available.'): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => $text]]]],
                ],
            ]),
        ]);
    }

    public function test_guest_without_a_question_gets_422_json_not_a_login_redirect(): void
    {
        Http::fake();

        $this->postJson(route('chat.ask'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('question');

        Http::assertNothingSent();
    }

    public function test_unconfigured_gemini_returns_answer_null_and_configured_false(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake();

        $this->postJson(route('chat.ask'), ['question' => 'What are your hours?'])
            ->assertOk()
            ->assertExactJson(['answer' => null, 'configured' => false]);

        Http::assertNothingSent();
    }

    public function test_payload_carries_business_knowledge_but_no_client_pii(): void
    {
        config(['services.gemini.key' => 'test-key']);
        $this->fakeGeminiSuccess('The lavender ritual is ₱4,321.09.');

        $service = Service::create([
            'name' => 'Zz Client Chat Lavender Ritual',
            'category' => 'Massage',
            'price' => 4321.09,
            'down_payment' => 250.00,
            'duration_minutes' => 75,
            'status' => 'active',
        ]);

        BusinessHours::updateOrCreate(
            ['day_of_week' => 3],
            ['open_at' => '08:37:00', 'close_at' => '23:41:00', 'is_open' => true]
        );

        $client = User::create([
            'name' => 'Qz Chatsecret Client',
            'email' => 'qz.chatsecret@example.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_CLIENT,
        ]);

        $faqQuestion = config('faqs.categories.0.question');
        $refusal = 'I can only help with Perfect Nails Wellness & Aesthetics — our services, prices, bookings, promos, and branch information. Feel free to ask me about those!';

        $this->actingAs($client)
            ->postJson(route('chat.ask'), ['question' => 'Tell me about the lavender ritual.'])
            ->assertOk()
            ->assertJson([
                'answer' => 'The lavender ritual is ₱4,321.09.',
                'configured' => true,
            ]);

        Http::assertSent(function ($request) use ($service, $faqQuestion, $refusal, $client) {
            $payload = json_encode($request->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->assertIsString($payload);

            $this->assertStringContainsString($service->name, $payload);
            $this->assertStringContainsString('4321.09', $payload);
            $this->assertStringContainsString('8:37 AM', $payload);
            $this->assertStringContainsString('11:41 PM', $payload);
            $this->assertStringContainsString($faqQuestion, $payload);
            $this->assertStringContainsString($refusal, $payload);

            $this->assertStringNotContainsString($client->name, $payload);
            $this->assertStringNotContainsString($client->email, $payload);

            return true;
        });
    }

    public function test_only_the_last_six_history_turns_are_sent(): void
    {
        config(['services.gemini.key' => 'test-key']);
        $this->fakeGeminiSuccess();

        $history = [];
        for ($i = 1; $i <= 10; $i++) {
            $history[] = [
                'role' => $i % 2 === 1 ? 'user' : 'assistant',
                'text' => 'customer-h-'.$i,
            ];
        }

        $this->postJson(route('chat.ask'), [
            'question' => 'What should I book?',
            'history' => $history,
        ])->assertOk();

        $contents = Http::recorded()[0][0]->data()['contents'];
        $texts = array_map(fn ($turn) => $turn['parts'][0]['text'], $contents);

        $this->assertCount(7, $texts);
        $this->assertSame('customer-h-5', $texts[0]);
        $this->assertSame('customer-h-10', $texts[5]);
        $this->assertSame('What should I book?', $texts[6]);
        $this->assertNotContains('customer-h-1', $texts);
        $this->assertNotContains('customer-h-4', $texts);
    }
}
