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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Financial Reports, monthly only (sha, 2026-10-29 — the Weekly tab was
 * removed): the page shows the current month. The Services by Revenue
 * table is the plain revenue ranking: no threshold, no limit. Time is
 * frozen to Wed 2026-10-28 so month boundaries are deterministic no
 * matter when the suite runs.
 */
class FinancialReportsTabsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $owner;

    private User $staff;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 10, 28, 12, 0, 0));
        $this->owner = User::where('email', 'admin@nexflio.test')->firstOrFail();
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

    public function test_defaults_to_monthly(): void
    {
        $this->verifiedPayment($this->service('Monthly Default Service', 100), 100, now());

        $this->actingAs($this->owner)->get(route('admin.reports.financial'))
            ->assertOk()
            ->assertSee('Revenue (Monthly)')
            ->assertSee('Services by Revenue')
            ->assertSee('Monthly Default Service');
    }

    public function test_services_table_has_no_limit_and_no_threshold(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            // One session each: below any threshold, beyond the old Top 10 cap.
            $this->verifiedPayment($this->service('Bulk Service '.$i, 100), 100, now());
        }

        $response = $this->actingAs($this->owner)->get(route('admin.reports.financial', ['period' => 'monthly']));
        $response->assertOk();
        for ($i = 1; $i <= 12; $i++) {
            $response->assertSee('Bulk Service '.$i);
        }
    }

    public function test_services_sort_by_revenue_desc(): void
    {
        $this->verifiedPayment($this->service('Sort Low Rev', 100), 100, now());
        $this->verifiedPayment($this->service('Sort High Rev', 500), 500, now());
        $this->verifiedPayment($this->service('Sort Mid Rev', 300), 300, now());

        $this->actingAs($this->owner)->get(route('admin.reports.financial', ['period' => 'monthly']))
            ->assertOk()
            ->assertSeeInOrder(['Sort High Rev', 'Sort Mid Rev', 'Sort Low Rev']);
    }

    public function test_export_covers_the_current_month(): void
    {
        $this->verifiedPayment($this->service('Export Month Service', 100), 100, now());

        $csv = $this->actingAs($this->owner)->get(route('admin.reports.financial.export', ['format' => 'csv']));
        $csv->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString('Monthly', $body);
        $this->assertStringContainsString('Services by Revenue', $body);
        $this->assertStringContainsString('Export Month Service', $body);

        $this->actingAs($this->owner)->get(route('admin.reports.financial.export', ['format' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function staffUser(string $name): User
    {
        // No User::factory(): the User model does not use HasFactory.
        // The users table only requires name, a unique email, password,
        // and role_id; the rest are nullable or defaulted.
        return User::create([
            'name' => $name,
            'email' => Str::slug($name).'@nexflio.test',
            'password' => Hash::make('password'),
            'role_id' => User::ROLE_STAFF,
        ]);
    }

    private function completedFor(User $personnel, Service $service, int $count, Carbon $date, float $price): void
    {
        for ($i = 0; $i < $count; $i++) {
            $appt = Appointment::create([
                'user_id' => $this->client->id,
                'service_id' => $service->id,
                'personnel_id' => $personnel->id,
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

    public function test_in_demand_staff_ranks_by_completed_services(): void
    {
        $service = $this->service('Staff Rank Service', 100);
        $this->completedFor($this->staffUser('Staff Gamma'), $service, 1, now(), 100);
        $this->completedFor($this->staffUser('Staff Alpha'), $service, 3, now(), 100);
        $this->completedFor($this->staffUser('Staff Beta'), $service, 2, now(), 100);

        $this->actingAs($this->owner)->get(route('admin.reports.financial', ['period' => 'monthly']))
            ->assertOk()
            ->assertSee('In-Demand Staff')
            ->assertSeeInOrder(['Staff Alpha', 'Staff Beta', 'Staff Gamma']);
    }

    public function test_export_includes_in_demand_staff(): void
    {
        $this->completedFor($this->staffUser('Staff Export'), $this->service('Staff Export Service', 100), 1, now(), 100);

        $response = $this->actingAs($this->owner)->get(route('admin.reports.financial.export', ['format' => 'csv', 'period' => 'monthly']));
        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertStringContainsString('In-Demand Staff', $body);
        $this->assertStringContainsString('Staff Export', $body);
    }
}
