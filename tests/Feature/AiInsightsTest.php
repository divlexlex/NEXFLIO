<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Web\DashboardController;
use App\Models\Appointment;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

/**
 * End-to-end coverage for the admin "AI Business Insights" card:
 *  - the insights endpoint with a faked Gemini response / no key / a 500
 *  - the batch-based low-stock metric
 *  - every new aggregate metric, computed by hand from a small seeded dataset
 *  - the guarantee that no client PII can reach the Gemini payload.
 *
 * Every HTTP call is faked — no real network, no real API key.
 */
class AiInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;
    private User $clientA;
    private User $clientB;
    private User $clientC;
    private Service $massage;
    private Service $facial;

    protected function setUp(): void
    {
        parent::setUp();

        // Frozen clock: Monday 2026-06-15 12:00. Weekday offsets below are
        // chosen so the expected weekday names are fixed.
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();

        $this->clientA = $this->makeClient('Zxqv Alpha', 'zxqv.alpha@example.test');
        $this->clientB = $this->makeClient('Zxqv Bravo', 'zxqv.bravo@example.test');
        $this->clientC = $this->makeClient('Zxqv Charlie', 'zxqv.charlie@example.test');

        $this->massage = Service::create([
            'name' => 'Test Massage',
            'category' => 'Massage',
            'price' => 1000.00,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);

        $this->facial = Service::create([
            'name' => 'Test Facial',
            'category' => 'Facial',
            'price' => 500.00,
            'duration_minutes' => 45,
            'status' => 'active',
        ]);
    }

    private function makeClient(string $name, string $email): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_CLIENT,
        ]);
    }

    private function makeAppointment(
        Service $service,
        ?User $client,
        int $daysAgo,
        string $time,
        AppointmentStatus $status,
        float $booked,
        ?string $walkInName = null,
        ?string $walkInPhone = null,
    ): Appointment {
        $when = now()->copy()->subDays($daysAgo);

        return Appointment::create([
            'user_id' => $client?->id,
            'walk_in_name' => $walkInName,
            'walk_in_phone' => $walkInPhone,
            'service_id' => $service->id,
            'booked_price' => $booked,
            'appointment_date' => $when->toDateTimeString(),
            'start_time' => $time,
            'status' => $status,
            'completed_at' => $status === AppointmentStatus::Completed
                ? $when->toDateTimeString()
                : null,
        ]);
    }

    private function verifiedPayment(Appointment $appointment, float $amount, int $daysAgo): Payment
    {
        return Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => $amount,
            'method' => 'gcash',
            'status' => PaymentStatus::Verified,
            'verified_at' => now()->copy()->subDays($daysAgo)->toDateTimeString(),
        ]);
    }

    /**
     * Frozen-clock dataset. Expected weekday: Mon 0, Sun -1, Sat -2, Fri -3,
     * Thu -4, Wed -5, Tue -6 (repeating every 7 days).
     */
    private function seedInsightsDataset(): void
    {
        // Last 30 days (5 bookings, 1 cancelled, 4 with value).
        $a1 = $this->makeAppointment($this->massage, $this->clientA, 5, '10:00', AppointmentStatus::Completed, 1000.00); // Wed
        $a2 = $this->makeAppointment($this->massage, $this->clientA, 10, '10:00', AppointmentStatus::Completed, 800.00); // Fri
        $a3 = $this->makeAppointment($this->facial, $this->clientB, 3, '14:00', AppointmentStatus::Completed, 500.00); // Fri
        $a4 = $this->makeAppointment($this->facial, $this->clientC, 1, '14:00', AppointmentStatus::Cancelled, 500.00); // Sun
        $a5 = $this->makeAppointment($this->massage, $this->clientC, 2, '10:00', AppointmentStatus::Booked, 600.00); // Sat

        // 30–90 days ago (counts toward the 90-day rhythm / repeat rate only).
        $a6 = $this->makeAppointment($this->massage, $this->clientB, 40, '09:00', AppointmentStatus::Completed, 1000.00); // Wed
        $a7 = $this->makeAppointment($this->facial, $this->clientC, 50, '15:00', AppointmentStatus::Completed, 500.00); // Sun
        $a8 = $this->makeAppointment($this->massage, $this->clientB, 45, '09:00', AppointmentStatus::Completed, 1000.00); // Fri

        // Rule A: verified money counts even on Booked a5. Last 30 days:
        // Massage 1000 + 800 + 600 = 2400, Facial 500.
        $this->verifiedPayment($a1, 1000.00, 5);
        $this->verifiedPayment($a2, 800.00, 9);
        $this->verifiedPayment($a3, 500.00, 3);
        $this->verifiedPayment($a5, 600.00, 2);

        $this->verifiedPayment($a6, 1000.00, 40);
        Payment::create([
            'appointment_id' => $a4->id,
            'amount' => 500.00,
            'method' => 'gcash',
            'status' => PaymentStatus::Pending,
        ]);
    }

    /**
     * The private weeklyMetrics() payload, JSON-normalized exactly like the
     * copy embedded in the Gemini request.
     *
     * @return array<string, mixed>
     */
    private function metrics(): array
    {
        $controller = app(DashboardController::class);
        $method = new ReflectionMethod($controller, 'weeklyMetrics');
        $method->setAccessible(true);

        return json_decode(json_encode($method->invoke($controller)), true);
    }

    private function fakeGeminiSuccess(string $text = 'Revenue is trending up this week.'): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => $text]]]],
                ],
            ]),
        ]);
    }

    // ===== Endpoint behaviour =====

    public function test_insights_endpoint_returns_fake_summary(): void
    {
        config(['services.gemini.key' => 'test-key']);
        $this->seedInsightsDataset();
        $this->fakeGeminiSuccess('Services are up and cancellations are low.');

        $this->actingAs($this->manager)
            ->getJson(route('admin.dashboard.insights'))
            ->assertOk()
            ->assertJson([
                'insight' => 'Services are up and cancellations are low.',
                'configured' => true,
            ]);
    }

    public function test_insights_endpoint_reports_unconfigured_without_calling_out(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake();

        $this->actingAs($this->manager)
            ->getJson(route('admin.dashboard.insights'))
            ->assertOk()
            ->assertJson(['insight' => null, 'configured' => false]);

        Http::assertNothingSent();
    }

    public function test_insights_endpoint_returns_null_on_gemini_500_but_stays_200(): void
    {
        config(['services.gemini.key' => 'test-key']);
        $this->seedInsightsDataset();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $this->actingAs($this->manager)
            ->getJson(route('admin.dashboard.insights'))
            ->assertOk()
            ->assertJson(['insight' => null, 'configured' => true]);
    }

    public function test_gemini_payload_contains_no_client_pii(): void
    {
        config(['services.gemini.key' => 'test-key']);
        $this->seedInsightsDataset();

        // A walk-in row carries the strongest PII in the schema.
        $this->makeAppointment(
            $this->facial,
            null,
            4,
            '11:00',
            AppointmentStatus::Completed,
            500.00,
            'Zxqv Walkin Person',
            '09998887777',
        );

        $this->fakeGeminiSuccess();

        $this->actingAs($this->manager)
            ->getJson(route('admin.dashboard.insights'))
            ->assertOk();

        $recorded = Http::recorded();
        $this->assertNotEmpty($recorded, 'The insights endpoint must have called Gemini.');

        $body = $recorded[0][0]->body();

        foreach ([
            'Zxqv Alpha',
            'zxqv.alpha@example.test',
            'Zxqv Walkin Person',
            '09998887777',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $body, "PII [{$needle}] leaked into the Gemini payload.");
        }

        // Sanity: the payload really is the aggregates (service names are allowed).
        $this->assertStringContainsString('revenue_this_week', $body);
        $this->assertStringContainsString('revenue_by_category_last_30_days', $body);
    }

    // ===== Low stock: batch totals, not the quantity cache =====

    public function test_low_stock_items_use_batch_totals_not_quantity_cache(): void
    {
        // Cache says "plenty" but the batches are nearly empty -> must be low.
        $low = Inventory::create([
            'item_name' => 'Zz Test Low',
            'category' => 'Nails',
            'unit' => 'pc',
            'quantity' => 100,
            'reorder_point' => 3,
            'price_per_unit' => 0,
        ]);
        $low->batches()->create([
            'quantity_received' => 3,
            'quantity_remaining' => 3,
            'unit_cost' => 0,
            'received_at' => now(),
        ]);

        // Cache says "empty" but the batches are healthy -> must not be low.
        $high = Inventory::create([
            'item_name' => 'Zz Test High',
            'category' => 'Nails',
            'unit' => 'pc',
            'quantity' => 0,
            'reorder_point' => 3,
            'price_per_unit' => 0,
        ]);
        $high->batches()->create([
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'unit_cost' => 0,
            'received_at' => now(),
        ]);

        $metrics = $this->metrics();

        $this->assertContains('Zz Test Low', $metrics['low_stock_items']);
        $this->assertNotContains('Zz Test High', $metrics['low_stock_items']);
    }

    // ===== New aggregate metrics =====

    public function test_new_metrics_are_correct_for_seeded_dataset(): void
    {
        $this->seedInsightsDataset();

        $metrics = $this->metrics();

        // Every new key exists.
        foreach ([
            'revenue_by_category_last_30_days',
            'bookings_by_day_of_week_last_90_days',
            'peak_day',
            'busiest_start_hours_last_90_days',
            'repeat_client_rate_last_90_days',
            'average_booking_value_last_30_days',
            'cancellation_rate_last_30_days',
        ] as $key) {
            $this->assertArrayHasKey($key, $metrics);
        }

        // Rule A (verified payments): Massage 1000+800+600 (a5 is Booked
        // but its ₱600 is verified, so it counts), Facial 500.
        $this->assertEquals(
            ['Massage' => 2400.0, 'Facial' => 500.0],
            $metrics['revenue_by_category_last_30_days']
        );

        // Weekday counts: Fri 3, Wed 2, Sun 2, Sat 1; peak = Friday.
        $this->assertEquals(3, $metrics['bookings_by_day_of_week_last_90_days']['Friday']);
        $this->assertEquals(2, $metrics['bookings_by_day_of_week_last_90_days']['Wednesday']);
        $this->assertEquals(2, $metrics['bookings_by_day_of_week_last_90_days']['Sunday']);
        $this->assertEquals(1, $metrics['bookings_by_day_of_week_last_90_days']['Saturday']);
        $this->assertSame('Friday', $metrics['peak_day']);

        // Top start hours: 10:00 x3, then 14:00 x2 and 09:00 x2.
        $this->assertCount(3, $metrics['busiest_start_hours_last_90_days']);
        $this->assertSame(
            [
                ['hour' => 10, 'count' => 3],
                ['hour' => 14, 'count' => 2],
                ['hour' => 9, 'count' => 2],
            ],
            $metrics['busiest_start_hours_last_90_days']
        );

        // Repeat clients: A=2, B=3, C=1 -> 2 of 3 = 66.7%.
        $this->assertEqualsWithDelta(66.7, $metrics['repeat_client_rate_last_90_days'], 0.001);

        // Average of non-cancelled booked_price, last 30 days: 2900 / 4 = 725.
        $this->assertEqualsWithDelta(725.0, $metrics['average_booking_value_last_30_days'], 0.001);

        // 1 cancelled of 5 -> 20%.
        $this->assertEqualsWithDelta(20.0, $metrics['cancellation_rate_last_30_days'], 0.001);

        // Existing keys keep working.
        $this->assertSame(5, $metrics['bookings_last_30_days']);
        $this->assertSame(3, $metrics['completed_last_30_days']);
        $this->assertSame(1, $metrics['cancellations_last_30_days']);
        $this->assertSame(0, $metrics['no_shows_last_30_days']);
        $this->assertEquals(['Test Massage' => 3, 'Test Facial' => 2], $metrics['top_services_last_30_days']);
    }
}
