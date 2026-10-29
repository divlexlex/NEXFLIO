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
 * Dashboard analytics, step 1 (sha, 2026-10-29): WTD/MTD/YTD revenue
 * cards shown together, plus Top Services (owner only) with WTD | MTD |
 * YTD pills. Top Services entry is >= 15 completed services in the
 * period; badges are labels only. Time is frozen to Wed 2026-10-28 so
 * week/month/year boundaries are deterministic no matter when the
 * suite runs.
 */
class DashboardTopServicesTest extends TestCase
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

    private function completedFor(Service $service, int $count, Carbon $date, float $price): void
    {
        for ($i = 0; $i < $count; $i++) {
            $appt = Appointment::create([
                'user_id' => $this->client->id,
                'service_id' => $service->id,
                'personnel_id' => $this->staff->id,
                'appointment_date' => $date->toDateString(),
                'start_time' => '13:00',
                'status' => AppointmentStatus::Completed,
            ]);
            Payment::create([
                'appointment_id' => $appt->id,
                'amount' => $price,
                'method' => 'gcash',
                'status' => PaymentStatus::Verified,
                'verified_at' => $date,
            ]);
        }
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

    public function test_owner_sees_top_services_and_revenue_cards(): void
    {
        $this->actingAs($this->owner)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Top Services')
            ->assertSee('Week to Date')
            ->assertSee('Month to Date')
            ->assertSee('Year to Date');
    }

    public function test_manager_does_not_see_top_services(): void
    {
        $this->actingAs($this->manager)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Top Services')
            ->assertSee('Week to Date')
            ->assertSee('Month to Date')
            ->assertSee('Year to Date');
    }

    public function test_top_services_requires_fifteen_completed(): void
    {
        $service = $this->service('Gate Service', 100);
        $this->completedFor($service, 14, now(), 100);

        $this->actingAs($this->owner)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('1 more')
            ->assertDontSee('>Top Performer<', false)
            ->assertDontSee('>Core<', false)
            ->assertDontSee('>Low<', false);

        $this->completedFor($service, 1, now(), 100);

        $this->actingAs($this->owner)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Gate Service')
            ->assertSee('>Top Performer<', false)
            ->assertDontSee('More needed');
    }

    public function test_top_services_ranks_by_count_then_revenue(): void
    {
        $this->completedFor($this->service('Rank Fifteen', 100), 15, now(), 100);
        $this->completedFor($this->service('Rank Twenty Low', 100), 20, now(), 100);
        $this->completedFor($this->service('Rank Twenty High', 500), 20, now(), 500);
        $this->completedFor($this->service('Rank Thirty', 100), 30, now(), 100);

        $this->actingAs($this->owner)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Rank Thirty', 'Rank Twenty High', 'Rank Twenty Low', 'Rank Fifteen']);
    }

    public function test_top_services_follows_the_period_pills(): void
    {
        $this->completedFor($this->service('Period This Week', 100), 16, Carbon::create(2026, 10, 28, 10, 0, 0), 100);
        $this->completedFor($this->service('Period Last Week', 100), 16, Carbon::create(2026, 10, 22, 10, 0, 0), 100);

        $wtd = $this->actingAs($this->owner)->get(route('admin.dashboard', ['top_period' => 'wtd']));
        $wtd->assertOk();
        $wtd->assertSee('WTD · Week of Oct 26, 2026');
        $wtd->assertSee('Period This Week');
        $wtd->assertDontSee('Period Last Week');

        $mtd = $this->actingAs($this->owner)->get(route('admin.dashboard', ['top_period' => 'mtd']));
        $mtd->assertOk();
        $mtd->assertSee('MTD · October 2026');
        $mtd->assertSee('Period This Week');
        $mtd->assertSee('Period Last Week');

        $this->actingAs($this->owner)->get(route('admin.dashboard', ['top_period' => 'ytd']))
            ->assertOk()
            ->assertSee('YTD · 2026')
            ->assertSee('Period This Week')
            ->assertSee('Period Last Week');
    }

    public function test_top_services_badges(): void
    {
        // 400 completed at ₱100: revenue shares equal volume shares.
        $this->completedFor($this->service('Badge Alpha', 100), 200, now(), 100);
        $this->completedFor($this->service('Badge Beta', 100), 100, now(), 100);
        $this->completedFor($this->service('Badge Gamma', 100), 50, now(), 100);
        $this->completedFor($this->service('Badge Delta', 100), 35, now(), 100);
        $this->completedFor($this->service('Badge Epsilon', 100), 15, now(), 100);

        $this->actingAs($this->owner)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Badge Alpha', 'Badge Beta', 'Badge Gamma', 'Badge Delta', 'Badge Epsilon'])
            ->assertSee('>Top Performer<', false)
            ->assertSee('>Core<', false)
            ->assertSee('>Low<', false);
    }

    public function test_revenue_cards_show_exact_period_sums(): void
    {
        $this->verifiedPayment($this->service('Revenue This Week', 20), 20, Carbon::create(2026, 10, 28, 10, 0, 0));
        $this->verifiedPayment($this->service('Revenue This Month', 1000), 1000, Carbon::create(2026, 10, 12, 10, 0, 0));
        $this->verifiedPayment($this->service('Revenue This Year', 500), 500, Carbon::create(2026, 2, 10, 10, 0, 0));

        $this->actingAs($this->owner)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('₱20.00')
            ->assertSee('₱1,020.00')
            ->assertSee('₱1,520.00');
    }

    public function test_top_period_defaults_to_mtd_and_invalid_falls_back(): void
    {
        $this->actingAs($this->owner)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('MTD · October 2026');

        $this->actingAs($this->owner)->get(route('admin.dashboard', ['top_period' => 'yearly']))
            ->assertOk()
            ->assertSee('MTD · October 2026');
    }
}
