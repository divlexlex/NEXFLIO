<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Attendance;
use App\Models\Commission;
use App\Models\Position;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\WeeklyPayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Weekly payroll (2026-10-24): Weekly Pay = Daily Base Pay × days
 * attended that week (Sun–Sat) + commissions earned that week.
 * Fixed week used below: Sunday 2026-10-18 – Saturday 2026-10-24
 * (Wednesday 2026-10-21 is the mid-week probe date).
 */
class WeeklyPayrollTest extends TestCase
{
    use RefreshDatabase;

    // Seeded like every other feature test: users.role_id is a foreign
    // key to the roles table, which only the seeders populate.
    protected $seed = true;

    private User $admin;
    private User $staff;
    private User $client;
    private Service $service;
    private int $commissionSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('email', 'admin@nexflio.test')->firstOrFail();
        $this->staff = User::create([
            'name' => 'Weekly Staff', 'email' => 'weekly@nexflio.test',
            'password' => bcrypt('password'), 'role_id' => User::ROLE_STAFF,
        ]);
        StaffProfile::create([
            'user_id' => $this->staff->id,
            'daily_base_pay' => 500,
            'commission_rate' => 10,
            'position' => 'Nail Technician',
            'employment_status' => 'active',
        ]);
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->service = Service::create([
            'name' => 'Gel Manicure', 'category' => 'Nails',
            'description' => 'Test service', 'price' => 750.00,
            'duration_minutes' => 60, 'status' => 'active',
        ]);
    }

    private function attend(string $date, bool $openShift = false): void
    {
        Attendance::create([
            'user_id' => $this->staff->id,
            'work_date' => $date,
            'time_in' => $date.' 09:00:00',
            'time_out' => $openShift ? null : $date.' 17:00:00',
        ]);
    }

    private function earn(float $amount, string $earnedAt): void
    {
        $this->commissionSeq++;
        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => substr($earnedAt, 0, 10),
            'start_time' => '10:00:00',
            'status' => AppointmentStatus::Completed,
        ]);

        Commission::create([
            'user_id' => $this->staff->id,
            'appointment_id' => $appointment->id,
            'service_price' => $amount * 10,
            'rate' => 10,
            'amount' => $amount,
            'earned_at' => $earnedAt,
        ]);
    }

    public function test_service_math_daily_times_attendance_plus_commission(): void
    {
        $this->attend('2026-10-19');
        $this->attend('2026-10-20');
        $this->attend('2026-10-21');
        $this->earn(300, '2026-10-21 15:00:00');

        $row = app(WeeklyPayrollService::class)->forStaff($this->staff, '2026-10-18');

        $this->assertSame(500.0, $row['daily_base_pay']);
        $this->assertSame(3, $row['days_present']);
        $this->assertSame(1500.0, $row['base_total']);
        $this->assertSame(300.0, $row['commission']);
        $this->assertSame(1800.0, $row['total']);
    }

    public function test_attendance_and_commission_outside_the_week_are_excluded(): void
    {
        // Saturday of the previous week and Sunday of the next week.
        $this->attend('2026-10-17');
        $this->attend('2026-10-25');
        $this->earn(999, '2026-10-17 23:59:59');
        $this->earn(999, '2026-10-25 00:00:00');
        // Boundary days of the target week itself do count.
        $this->attend('2026-10-18');
        $this->attend('2026-10-24');
        $this->earn(100, '2026-10-18 00:00:00');
        $this->earn(100, '2026-10-24 23:59:59');

        $row = app(WeeklyPayrollService::class)->forStaff($this->staff, '2026-10-18');

        $this->assertSame(2, $row['days_present']);
        $this->assertSame(1000.0, $row['base_total']);
        $this->assertSame(200.0, $row['commission']);
        $this->assertSame(1200.0, $row['total']);
    }

    public function test_open_shift_still_counts_as_a_full_paid_day(): void
    {
        $this->attend('2026-10-19', openShift: true);

        $row = app(WeeklyPayrollService::class)->forStaff($this->staff, '2026-10-18');

        $this->assertSame(1, $row['days_present']);
        $this->assertSame(500.0, $row['base_total']);
    }

    public function test_no_attendance_means_zero_base_and_commission_only(): void
    {
        $this->earn(250, '2026-10-20 12:00:00');

        $row = app(WeeklyPayrollService::class)->forStaff($this->staff, '2026-10-18');

        $this->assertSame(0, $row['days_present']);
        $this->assertSame(0.0, $row['base_total']);
        $this->assertSame(250.0, $row['total']);
    }

    public function test_week_range_normalizes_a_wednesday_to_sunday_saturday(): void
    {
        [$start, $end] = app(WeeklyPayrollService::class)->weekRange('2026-10-21');

        $this->assertSame('2026-10-18', $start);
        $this->assertSame('2026-10-24', $end);
    }

    public function test_billing_page_shows_weekly_payroll_for_the_selected_week(): void
    {
        $this->attend('2026-10-19');
        $this->attend('2026-10-20');
        $this->attend('2026-10-21');
        $this->earn(300, '2026-10-21 15:00:00');

        $this->actingAs($this->admin)->get('/admin/billing?week=2026-10-21')
            ->assertOk()
            ->assertSee('Daily Base Pay')
            ->assertSee('Days Present')
            ->assertSee('Weekly Staff')
            ->assertSee('1,800.00');

        // A different week shows different (empty) numbers.
        $this->actingAs($this->admin)->get('/admin/billing?week=2026-10-28')
            ->assertOk()
            ->assertDontSee('1,800.00');
    }

    public function test_api_my_commissions_returns_the_weekly_contract(): void
    {
        $this->attend('2026-10-19');
        $this->attend('2026-10-20');
        $this->attend('2026-10-21');
        $this->earn(300, '2026-10-21 15:00:00');

        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/my-commissions?week=2026-10-21');

        $response->assertOk()
            ->assertJsonPath('week_start', '2026-10-18')
            ->assertJsonPath('week_end', '2026-10-24')
            ->assertJsonPath('daily_base_pay', '500.00')
            ->assertJsonPath('days_present', 3)
            ->assertJsonPath('base_total', '1500.00')
            ->assertJsonPath('commission_total', '300.00')
            ->assertJsonPath('grand_total', '1800.00');
        $this->assertCount(1, $response->json('entries'));
    }

    public function test_employee_create_saves_daily_base_pay_and_page_labels_it_daily(): void
    {
        Position::firstOrCreate(['name' => 'Nail Technician'], ['service_category' => 'Nails']);

        $this->actingAs($this->admin)->post(route('admin.employees.store'), [
            'first_name' => 'Daily',
            'last_name' => 'Rate',
            'contact_number' => '09171234567',
            'position' => 'Nail Technician',
            'daily_base_pay' => 550,
            'commission_rate' => 10,
            'hired_at' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $created = User::where('email', 'rate.daily@nexflio.test')->firstOrFail();
        $this->assertSame('550.00', (string) $created->staffProfile->daily_base_pay);

        $this->actingAs($this->admin)->get(route('admin.employees'))
            ->assertOk()
            ->assertSee('Daily Base Pay');
    }
}
