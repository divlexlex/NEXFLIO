<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Dashboard revenue overview custom date filter (Lyka, 2026-10-30):
 * a specific From–To window on the Weekly Revenue section for
 * peak-season tracking. Revenue-only (Rule A, verified payments by
 * verified_at): it changes the custom total, the peak day, and the
 * revenue chart (daily totals in the window), never the WTD/MTD/YTD
 * cards or Top Services. An incomplete or invalid range shows an
 * error and falls back to the default weekly chart instead of
 * breaking the page. Time is frozen to Wed 2026-10-28 so the
 * WTD week label in the coexistence test is deterministic.
 */
class DashboardRevenueFilterTest extends TestCase
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
        Carbon::setTestNow(Carbon::create(2026, 10, 28, 12, 0, 0));
        $this->owner = User::where('email', 'admin@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(string $name, float $price): Service
    {
        return Service::create([
            'name' => $name, 'category' => 'Nails', 'price' => $price,
            'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
    }

    private function verifiedPayment(Service $service, float $amount, Carbon $verifiedAt): void
    {
        $appt = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => $verifiedAt->toDateString(),
            'start_time' => '13:00',
            'status' => AppointmentStatus::Completed,
        ]);
        Payment::create([
            'appointment_id' => $appt->id,
            'amount' => $amount,
            'method' => 'gcash',
            'status' => PaymentStatus::Verified,
            'verified_at' => $verifiedAt,
        ]);
    }

    private function septemberPayments(): void
    {
        $service = $this->service('Aromatherapy Massage', 500);
        $this->verifiedPayment($service, 100, Carbon::create(2026, 9, 14, 10, 0));
        $this->verifiedPayment($service, 900, Carbon::create(2026, 9, 17, 10, 0));
        $this->verifiedPayment($service, 200, Carbon::create(2026, 9, 20, 10, 0));
        // Outside the Sept 14–20 window: must not count.
        $this->verifiedPayment($service, 5000, Carbon::create(2026, 9, 21, 10, 0));
    }

    public function test_no_filter_shows_default_weekly_chart_and_filter_form(): void
    {
        $this->actingAs($this->owner)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Weekly Revenue (verified payments)')
            ->assertSee('Apply filter')
            ->assertDontSee('Revenue in selected range');
    }

    public function test_valid_range_shows_total_and_peak_day(): void
    {
        $this->septemberPayments();

        $this->actingAs($this->owner)
            ->get(route('admin.dashboard', ['rev_from' => '2026-09-14', 'rev_to' => '2026-09-20']))
            ->assertOk()
            ->assertSee('Revenue · Sep 14, 2026 – Sep 20, 2026 (verified payments)')
            ->assertSee('Revenue in selected range')
            ->assertSee('₱1,200.00')
            ->assertSee('Sep 17')
            ->assertSee('₱900.00');
    }

    public function test_empty_range_shows_no_revenue_message(): void
    {
        $this->actingAs($this->owner)
            ->get(route('admin.dashboard', ['rev_from' => '2026-01-05', 'rev_to' => '2026-01-11']))
            ->assertOk()
            ->assertSee('Revenue · Jan 5, 2026 – Jan 11, 2026 (verified payments)')
            ->assertSee('No verified revenue in this range.');
    }

    public function test_from_after_to_shows_error_and_default_chart(): void
    {
        $this->actingAs($this->owner)
            ->get(route('admin.dashboard', ['rev_from' => '2026-09-20', 'rev_to' => '2026-09-14']))
            ->assertOk()
            ->assertSee('From date must be on or before To date.')
            ->assertSee('Weekly Revenue (verified payments)')
            ->assertDontSee('Revenue in selected range');
    }

    public function test_only_one_date_shows_error(): void
    {
        $this->actingAs($this->owner)
            ->get(route('admin.dashboard', ['rev_from' => '2026-09-14']))
            ->assertOk()
            ->assertSee('Select both a From date and a To date.')
            ->assertSee('Weekly Revenue (verified payments)')
            ->assertDontSee('Revenue in selected range');
    }

    public function test_chart_data_returns_daily_totals_for_range(): void
    {
        $this->septemberPayments();

        $response = $this->actingAs($this->owner)
            ->getJson(route('admin.dashboard.data', ['rev_from' => '2026-09-14', 'rev_to' => '2026-09-20']))
            ->assertOk();

        $this->assertSame(
            ['Sep 14', 'Sep 15', 'Sep 16', 'Sep 17', 'Sep 18', 'Sep 19', 'Sep 20'],
            $response->json('revenue_weeks')
        );
        $this->assertEquals(
            [100, 0, 0, 900, 0, 0, 200],
            $response->json('revenue_totals')
        );
    }

    public function test_chart_data_without_filter_returns_weekly_totals(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson(route('admin.dashboard.data'))
            ->assertOk();

        $this->assertCount(8, $response->json('revenue_weeks'));
        $this->assertCount(8, $response->json('revenue_totals'));
    }

    public function test_manager_sees_revenue_filter_but_not_top_services(): void
    {
        $this->septemberPayments();

        $this->actingAs($this->manager)
            ->get(route('admin.dashboard', ['rev_from' => '2026-09-14', 'rev_to' => '2026-09-20']))
            ->assertOk()
            ->assertSee('Apply filter')
            ->assertSee('₱1,200.00')
            ->assertDontSee('Top Services');
    }

    public function test_filter_preserves_top_services_period(): void
    {
        $this->septemberPayments();

        $this->actingAs($this->owner)
            ->get(route('admin.dashboard', ['top_period' => 'wtd', 'rev_from' => '2026-09-14', 'rev_to' => '2026-09-20']))
            ->assertOk()
            ->assertSee('WTD · Week of Oct 26, 2026')
            ->assertSee('₱1,200.00');
    }

    public function test_dashboard_ajax_hooks_present(): void
    {
        // The no-reload behaviour itself is JavaScript (verified
        // visually); this test pins the hooks it depends on: the two
        // swappable card ids, the filter form id, and the Clear link
        // marker. If any id is renamed, the AJAX swap silently stops.
        $this->actingAs($this->owner)
            ->get(route('admin.dashboard', ['rev_from' => '2026-09-14', 'rev_to' => '2026-09-20']))
            ->assertOk()
            ->assertSee('id="top-services-card"', false)
            ->assertSee('id="revenue-overview-card"', false)
            ->assertSee('id="revenue-filter-form"', false)
            ->assertSee('data-dashboard-clear', false);
    }
}
