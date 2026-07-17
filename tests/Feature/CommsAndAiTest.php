<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Jobs\SendPushNotification;
use App\Mail\AppointmentReminderMail;
use App\Mail\BookingReceivedMail;
use App\Mail\PaymentRejectedMail;
use App\Mail\PaymentVerifiedMail;
use App\Models\Appointment;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\Service;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommsAndAiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;
    private User $staff;
    private User $client;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();

        $this->service = Service::create([
            'name' => 'Signature Facial',
            'category' => 'Aesthetics',
            'description' => 'Test',
            'price' => 1200.00,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);
    }

    private function bookViaApi(): Appointment
    {
        Storage::fake('public');

        $response = $this->actingAs($this->client, 'sanctum')->postJson('/api/appointments', [
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '15:00',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ]);
        $response->assertSuccessful();

        return Appointment::findOrFail($response->json('id'));
    }

    // ===== Email + push side effects =====

    public function test_booking_queues_received_email_and_push(): void
    {
        Mail::fake();
        Queue::fake();

        $this->bookViaApi();

        Mail::assertQueued(BookingReceivedMail::class, fn ($mail) => $mail->hasTo($this->client->email));
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->client->id);
        $this->assertSame(1, Notification::where('user_id', $this->client->id)->count());
    }

    public function test_verification_queues_verified_email(): void
    {
        $appointment = $this->bookViaApi();

        Mail::fake();
        Queue::fake();

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'booked'])
            ->assertOk();

        Mail::assertQueued(PaymentVerifiedMail::class, fn ($mail) => $mail->hasTo($this->client->email));
        Queue::assertPushed(SendPushNotification::class);
    }

    public function test_rejection_queues_rejected_email_with_reason(): void
    {
        $appointment = $this->bookViaApi();

        Mail::fake();
        Queue::fake();

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", [
                'status' => 'cancelled',
                'rejection_reason' => 'Wrong amount on the receipt.',
            ])->assertOk();

        Mail::assertQueued(
            PaymentRejectedMail::class,
            fn ($mail) => $mail->reason === 'Wrong amount on the receipt.'
        );
    }

    public function test_reminder_command_notifies_tomorrows_booked_clients(): void
    {
        $appointment = $this->bookViaApi();
        $appointment->update(['status' => AppointmentStatus::Booked]);

        Mail::fake();
        Queue::fake();

        $this->artisan('appointments:remind')->assertSuccessful();

        Mail::assertQueued(AppointmentReminderMail::class, fn ($mail) => $mail->hasTo($this->client->email));
        $this->assertTrue(
            Notification::where('user_id', $this->client->id)
                ->where('title', 'Appointment reminder')
                ->exists()
        );
    }

    // ===== Device tokens =====

    public function test_device_token_register_and_unregister(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/device-tokens', ['token' => 'fcm-token-123', 'platform' => 'android'])
            ->assertCreated();

        $this->assertSame(1, DeviceToken::where('user_id', $this->client->id)->count());

        // Another user signing in on the same device re-claims the token.
        $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/device-tokens', ['token' => 'fcm-token-123'])
            ->assertCreated();
        $this->assertSame($this->staff->id, DeviceToken::where('token', 'fcm-token-123')->first()->user_id);

        $this->actingAs($this->staff, 'sanctum')
            ->deleteJson('/api/device-tokens', ['token' => 'fcm-token-123'])
            ->assertOk();
        $this->assertSame(0, DeviceToken::count());
    }

    // ===== Dialogflow webhook =====

    public function test_dialogflow_webhook_requires_token(): void
    {
        config(['services.dialogflow.webhook_token' => 'secret-token']);

        $this->postJson('/api/dialogflow/webhook', [])->assertUnauthorized();

        $this->withHeader('X-Webhook-Token', 'wrong')
            ->postJson('/api/dialogflow/webhook', [])
            ->assertUnauthorized();
    }

    public function test_dialogflow_services_intent_lists_active_services(): void
    {
        config(['services.dialogflow.webhook_token' => 'secret-token']);

        $response = $this->withHeader('X-Webhook-Token', 'secret-token')
            ->postJson('/api/dialogflow/webhook', [
                'queryResult' => [
                    'intent' => ['displayName' => 'services.prices'],
                    'parameters' => ['category' => 'Aesthetics'],
                ],
            ]);

        $response->assertOk();
        $this->assertStringContainsString('Signature Facial', $response->json('fulfillmentText'));
        $this->assertStringContainsString('1,200.00', $response->json('fulfillmentText'));
    }

    // ===== Gemini =====

    public function test_gemini_returns_null_when_unconfigured(): void
    {
        config(['services.gemini.key' => null]);

        $this->assertNull(app(GeminiService::class)->summarizeOperations(['x' => 1]));
    }

    public function test_gemini_returns_text_on_success(): void
    {
        config(['services.gemini.key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Revenue is trending up this week.']]]],
                ],
            ]),
        ]);

        $this->assertSame(
            'Revenue is trending up this week.',
            app(GeminiService::class)->summarizeOperations(['revenue' => 100])
        );
    }

    public function test_gemini_returns_null_on_api_failure(): void
    {
        config(['services.gemini.key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'quota'], 429),
        ]);

        $this->assertNull(app(GeminiService::class)->summarizeOperations(['revenue' => 100]));
    }

    public function test_dashboard_insights_endpoint_degrades_gracefully(): void
    {
        config(['services.gemini.key' => null]);

        $this->actingAs($this->manager)
            ->getJson(route('admin.dashboard.insights'))
            ->assertOk()
            ->assertJson(['insight' => null, 'configured' => false]);
    }
}
