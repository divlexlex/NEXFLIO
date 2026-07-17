<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Commission;
use App\Models\Inventory;
use App\Models\LeaveRequest;
use App\Models\Service;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffOperationsTest extends TestCase
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
            'name' => 'Gel Manicure',
            'category' => 'Nails',
            'description' => 'Test service',
            'price' => 750.00,
            'duration_minutes' => 60,
            'status' => 'active',
        ]);
    }

    private function bookedAppointment(): Appointment
    {
        Storage::fake('public');

        $response = $this->actingAs($this->client, 'sanctum')->postJson('/api/appointments', [
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '14:00',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ]);
        $response->assertSuccessful();

        $appointment = Appointment::findOrFail($response->json('id'));

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'booked'])
            ->assertOk();

        return $appointment->fresh();
    }

    // ===== Attendance =====

    public function test_time_in_and_out_once_per_day(): void
    {
        $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/attendance/time-in')
            ->assertCreated();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson('/api/attendance/time-in')
            ->assertStatus(409);

        $this->actingAs($this->staff, 'sanctum')
            ->patchJson('/api/attendance/time-out')
            ->assertOk();

        $this->actingAs($this->staff, 'sanctum')
            ->patchJson('/api/attendance/time-out')
            ->assertStatus(409);
    }

    public function test_clients_cannot_use_attendance(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/attendance/time-in')
            ->assertForbidden();
    }

    // ===== Booking pool =====

    public function test_break_removes_staff_from_booking_pool(): void
    {
        $pool = $this->actingAs($this->client, 'sanctum')->getJson('/api/personnel');
        $this->assertContains($this->staff->id, array_column($pool->json(), 'id'));

        $this->actingAs($this->staff, 'sanctum')
            ->patchJson('/api/staff/break')
            ->assertOk()
            ->assertJson(['is_on_break' => true]);

        $pool = $this->actingAs($this->client, 'sanctum')->getJson('/api/personnel');
        $this->assertNotContains($this->staff->id, array_column($pool->json(), 'id'));

        // Toggle back
        $this->actingAs($this->staff, 'sanctum')->patchJson('/api/staff/break')->assertOk();
        $pool = $this->actingAs($this->client, 'sanctum')->getJson('/api/personnel');
        $this->assertContains($this->staff->id, array_column($pool->json(), 'id'));
    }

    public function test_approved_leave_removes_staff_from_pool_on_those_dates(): void
    {
        $leaveDate = now()->addDays(3)->toDateString();

        $response = $this->actingAs($this->staff, 'sanctum')->postJson('/api/leaves', [
            'start_date' => $leaveDate,
            'end_date' => $leaveDate,
            'type' => 'vacation',
            'reason' => 'Family trip',
        ]);
        $response->assertCreated();
        $leaveId = $response->json('leave.id');

        // Pending leave does not block bookings yet.
        $pool = $this->actingAs($this->client, 'sanctum')->getJson("/api/personnel?date={$leaveDate}");
        $this->assertContains($this->staff->id, array_column($pool->json(), 'id'));

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/leaves/{$leaveId}/review", ['status' => 'approved'])
            ->assertOk();

        $pool = $this->actingAs($this->client, 'sanctum')->getJson("/api/personnel?date={$leaveDate}");
        $this->assertNotContains($this->staff->id, array_column($pool->json(), 'id'));

        // Other dates remain bookable.
        $otherDate = now()->addDays(5)->toDateString();
        $pool = $this->actingAs($this->client, 'sanctum')->getJson("/api/personnel?date={$otherDate}");
        $this->assertContains($this->staff->id, array_column($pool->json(), 'id'));
    }

    public function test_reviewed_leave_cannot_be_reviewed_again(): void
    {
        $leaveDate = now()->addDays(3)->toDateString();
        $leave = LeaveRequest::create([
            'user_id' => $this->staff->id,
            'start_date' => $leaveDate,
            'end_date' => $leaveDate,
            'type' => 'sick',
            'reason' => 'Flu',
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/leaves/{$leave->id}/review", ['status' => 'denied'])
            ->assertStatus(422);
    }

    // ===== Service lifecycle: commission + inventory =====

    public function test_completion_records_commission_once_and_consumes_inventory_fifo(): void
    {
        // Stock an item with two batches so consumption crosses none.
        $inventory = Inventory::create([
            'item_name' => 'Gel Polish',
            'unit' => 'bottle',
            'quantity' => 0,
            'reorder_point' => 1,
            'price_per_unit' => 200.00,
        ]);
        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/inventory/{$inventory->id}/batches", ['quantity' => 3, 'unit_cost' => 180.00])
            ->assertCreated();

        $appointment = $this->bookedAppointment();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/start")
            ->assertOk();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", [
                'items' => [
                    ['inventory_id' => $inventory->id, 'quantity' => 1],
                ],
            ])
            ->assertOk();

        // Commission: exactly one row, 10% of 750.00
        $commissions = Commission::where('appointment_id', $appointment->id)->get();
        $this->assertCount(1, $commissions);
        $this->assertSame($this->staff->id, $commissions->first()->user_id);
        $this->assertSame('75.00', (string) $commissions->first()->amount);

        // Inventory: consumed one bottle, movement referencing the appointment
        $this->assertSame(2, (int) $inventory->fresh()->quantity);
        $movement = StockMovement::where('type', 'out')
            ->where('reference_type', Appointment::class)
            ->where('reference_id', $appointment->id)
            ->first();
        $this->assertNotNull($movement);
        $this->assertSame(1, (int) $movement->quantity);

        // Completed is terminal — a repeat completion cannot double-pay.
        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete")
            ->assertStatus(422);
        $this->assertSame(1, Commission::where('appointment_id', $appointment->id)->count());
    }

    public function test_completion_fails_atomically_when_stock_is_insufficient(): void
    {
        $inventory = Inventory::create([
            'item_name' => 'Cuticle Oil',
            'unit' => 'bottle',
            'quantity' => 0,
            'reorder_point' => 1,
            'price_per_unit' => 90.00,
        ]);

        $appointment = $this->bookedAppointment();
        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/start")
            ->assertOk();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/appointments/{$appointment->id}/complete", [
                'items' => [
                    ['inventory_id' => $inventory->id, 'quantity' => 2],
                ],
            ])
            ->assertStatus(422);

        // Nothing was half-applied.
        $this->assertSame(AppointmentStatus::InService, $appointment->fresh()->status);
        $this->assertSame(0, Commission::where('appointment_id', $appointment->id)->count());
    }

    // ===== Commission view =====

    public function test_staff_sees_base_pay_plus_commission_total(): void
    {
        $appointment = $this->bookedAppointment();
        $this->actingAs($this->staff, 'sanctum')->postJson("/api/appointments/{$appointment->id}/start")->assertOk();
        $this->actingAs($this->staff, 'sanctum')->postJson("/api/appointments/{$appointment->id}/complete")->assertOk();

        $response = $this->actingAs($this->staff, 'sanctum')->getJson('/api/my-commissions');
        $response->assertOk();

        $this->assertSame('12000.00', $response->json('base_pay'));
        $this->assertSame('75.00', $response->json('commission_total'));
        $this->assertSame('12075.00', $response->json('grand_total'));
        $this->assertCount(1, $response->json('entries'));
    }

    // ===== Visibility =====

    public function test_staff_only_sees_their_assigned_appointments(): void
    {
        $this->bookedAppointment();

        $otherStaff = User::create([
            'name' => 'Second Staff',
            'email' => 'staff2@nexflio.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_STAFF,
        ]);

        $response = $this->actingAs($otherStaff, 'sanctum')->getJson('/api/appointments');
        $response->assertOk();
        $this->assertCount(0, $response->json());

        $response = $this->actingAs($this->staff, 'sanctum')->getJson('/api/appointments');
        $this->assertCount(1, $response->json());
    }

    public function test_staff_can_read_inventory_options_but_not_full_inventory(): void
    {
        $this->actingAs($this->staff, 'sanctum')->getJson('/api/inventory/options')->assertOk();
        $this->actingAs($this->staff, 'sanctum')->getJson('/api/inventory')->assertForbidden();
    }
}
