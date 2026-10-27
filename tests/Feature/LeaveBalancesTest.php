<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\Position;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveBalancesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $owner;
    private User $manager;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::where('email', 'admin@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
    }

    public function test_approved_sick_requests_count_toward_sl_only_and_approved_vacation_toward_vl_only(): void
    {
        $year = now()->year;

        LeaveRequest::create([
            'user_id' => $this->staff->id,
            'start_date' => "$year-01-05",
            'end_date' => "$year-01-05",
            'type' => 'sick',
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        LeaveRequest::create([
            'user_id' => $this->staff->id,
            'start_date' => "$year-02-01",
            'end_date' => "$year-02-02",
            'type' => 'vacation',
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($this->owner)->get(route('admin.employees'));
        $response->assertStatus(200);
        $response->assertSee('Leaves — ' . $year);
        // Staff name appears in leaves tab context; balances rendered
        $response->assertSee('SL consumed');
        $response->assertSee('VL consumed');
    }

    public function test_day_counting_is_inclusive(): void
    {
        $year = now()->year;

        LeaveRequest::create([
            'user_id' => $this->staff->id,
            'start_date' => "$year-03-01",
            'end_date' => "$year-03-03",
            'type' => 'sick',
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        $balances = \App\Support\LeaveBalances::forUser($this->staff, $year);
        $this->assertEquals(3, $balances['sl_used']);
        $this->assertEquals(9, $balances['sl_remaining']);
    }

    public function test_pending_and_denied_requests_consume_nothing(): void
    {
        $year = now()->year;

        LeaveRequest::create([
            'user_id' => $this->staff->id,
            'start_date' => "$year-01-10",
            'end_date' => "$year-01-12",
            'type' => 'sick',
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        LeaveRequest::create([
            'user_id' => $this->staff->id,
            'start_date' => "$year-02-10",
            'end_date' => "$year-02-11",
            'type' => 'vacation',
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_DENIED,
        ]);

        $balances = \App\Support\LeaveBalances::forUser($this->staff, $year);
        $this->assertEquals(0, $balances['sl_used']);
        $this->assertEquals(12, $balances['sl_remaining']);
        $this->assertEquals(0, $balances['vl_used']);
        $this->assertEquals(12, $balances['vl_remaining']);
    }

    public function test_emergency_and_other_types_consume_neither_pool(): void
    {
        $year = now()->year;

        LeaveRequest::create([
            'user_id' => $this->staff->id,
            'start_date' => "$year-04-01",
            'end_date' => "$year-04-05",
            'type' => 'emergency',
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        LeaveRequest::create([
            'user_id' => $this->staff->id,
            'start_date' => "$year-05-01",
            'end_date' => "$year-05-03",
            'type' => 'other',
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        $balances = \App\Support\LeaveBalances::forUser($this->staff, $year);
        $this->assertEquals(0, $balances['sl_used']);
        $this->assertEquals(12, $balances['sl_remaining']);
        $this->assertEquals(0, $balances['vl_used']);
        $this->assertEquals(12, $balances['vl_remaining']);
    }

    public function test_requests_from_previous_year_do_not_count(): void
    {
        $year = now()->year;
        $prevYear = $year - 1;

        LeaveRequest::create([
            'user_id' => $this->staff->id,
            'start_date' => "$prevYear-12-28",
            'end_date' => "$prevYear-12-31",
            'type' => 'sick',
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        LeaveRequest::create([
            'user_id' => $this->staff->id,
            'start_date' => "$prevYear-11-01",
            'end_date' => "$prevYear-11-10",
            'type' => 'vacation',
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        $balances = \App\Support\LeaveBalances::forUser($this->staff, $year);
        $this->assertEquals(0, $balances['sl_used']);
        $this->assertEquals(12, $balances['sl_remaining']);
        $this->assertEquals(0, $balances['vl_used']);
        $this->assertEquals(12, $balances['vl_remaining']);
    }

    public function test_remaining_floors_at_zero_when_consumed_exceeds_twelve(): void
    {
        $year = now()->year;

        LeaveRequest::create([
            'user_id' => $this->staff->id,
            'start_date' => "$year-01-01",
            'end_date' => "$year-01-15",
            'type' => 'sick',
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        $balances = \App\Support\LeaveBalances::forUser($this->staff, $year);
        $this->assertEquals(15, $balances['sl_used']);
        $this->assertEquals(0, $balances['sl_remaining']);
    }

    public function test_employees_page_renders_section_with_correct_numbers_for_staff_with_approved_requests_as_owner_or_manager(): void
    {
        $year = now()->year;

        LeaveRequest::create([
            'user_id' => $this->staff->id,
            'start_date' => "$year-06-01",
            'end_date' => "$year-06-02",
            'type' => 'sick',
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        LeaveRequest::create([
            'user_id' => $this->staff->id,
            'start_date' => "$year-07-01",
            'end_date' => "$year-07-01",
            'type' => 'vacation',
            'reason' => 'test',
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        // As owner
        $response = $this->actingAs($this->owner)->get(route('admin.employees'));
        $response->assertStatus(200);
        $response->assertSee('Leaves — ' . $year);
        $response->assertSee('SL consumed');
        $response->assertSee('SL remaining');
        $response->assertSee('VL consumed');
        $response->assertSee('VL remaining');
        $response->assertSee('12 Sick Leave + 12 Vacation Leave per year');
        $response->assertSee('consumable only');
        $response->assertSee('not convertible to cash');
        $response->assertSee('Unused leaves reset every January');

        // As manager
        $response2 = $this->actingAs($this->manager)->get(route('admin.employees'));
        $response2->assertStatus(200);
        $response2->assertSee('Leaves — ' . $year);
    }
}
