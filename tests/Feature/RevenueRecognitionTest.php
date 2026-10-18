<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\RevenueRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Recognized revenue = Completed appointments only, dated by completed_at.
 * Walk-in cash verification at booking is collection, not recognition.
 */
class RevenueRecognitionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;

    private User $staff;

    private Service $service;

    private RevenueRecognitionService $revenue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->revenue = app(RevenueRecognitionService::class);

        // Seeded staff@nexflio.test is a Massage Technician — category must match.
        $this->service = Service::create([
            'name' => 'Revenue Massage',
            'category' => 'Massage',
            'description' => 'Test',
            'price' => 500.00,
            'duration_minutes' => 45,
            'status' => 'active',
        ]);
    }

    private function createWalkIn(): Appointment
    {
        $this->actingAs($this->manager)->post(route('admin.appointments.walk-in'), [
            'walk_in_name' => 'Walk-in Guest',
            'walk_in_phone' => '09171234567',
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->toDateString(),
            'start_time' => '10:00',
        ])->assertSessionHasNoErrors();

        return Appointment::where('walk_in_name', 'Walk-in Guest')->latest('id')->firstOrFail();
    }

    private function transition(Appointment $appointment, AppointmentStatus $target): void
    {
        app(AppointmentService::class)->transition($appointment, $target, $this->manager);
    }

    public function test_creating_walk_in_does_not_increase_recognized_revenue(): void
    {
        $before = $this->revenue->sumBetween();

        $appointment = $this->createWalkIn();

        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertSame(PaymentStatus::Verified, $appointment->payment->status);
        $this->assertSame('500.00', (string) $appointment->payment->amount);
        $this->assertNull($appointment->completed_at);
        $this->assertEquals($before, $this->revenue->sumBetween());
        $this->assertEquals(0.0, $this->revenue->amountFor($appointment->fresh()));
    }

    public function test_confirm_and_start_do_not_increase_recognized_revenue(): void
    {
        // Online-style path: Unverified → Booked → InService (no revenue yet).
        $appointment = Appointment::create([
            'user_id' => User::where('email', 'client@nexflio.test')->firstOrFail()->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'booked_price' => 500,
            'appointment_date' => now()->toDateString(),
            'start_time' => '11:00',
            'status' => AppointmentStatus::Unverified,
        ]);
        $appointment->payment()->create([
            'amount' => 500,
            'method' => 'gcash',
            'status' => PaymentStatus::Pending,
        ]);

        $before = $this->revenue->sumBetween();

        $this->transition($appointment->fresh(), AppointmentStatus::Booked);
        $this->assertEquals($before, $this->revenue->sumBetween());

        $this->transition($appointment->fresh(), AppointmentStatus::InService);
        $this->assertEquals($before, $this->revenue->sumBetween());
        $this->assertNull($appointment->fresh()->completed_at);
    }

    public function test_completing_walk_in_recognizes_revenue_exactly_once(): void
    {
        $appointment = $this->createWalkIn();
        $before = $this->revenue->sumBetween();

        $this->transition($appointment->fresh(), AppointmentStatus::InService);
        $this->assertEquals($before, $this->revenue->sumBetween());

        $this->transition($appointment->fresh(), AppointmentStatus::Completed);

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Completed, $appointment->status);
        $this->assertNotNull($appointment->completed_at);
        $this->assertEquals(round($before + 500, 2), $this->revenue->sumBetween());
        $this->assertEquals(500.0, $this->revenue->amountFor($appointment));

        // Second completion is rejected — no double count.
        try {
            $this->transition($appointment->fresh(), AppointmentStatus::Completed);
            $this->fail('Expected invalid transition from Completed to Completed');
        } catch (\Illuminate\Validation\ValidationException) {
            // expected
        }

        $this->assertEquals(round($before + 500, 2), $this->revenue->sumBetween());
    }

    public function test_cancelling_pending_or_confirmed_walk_in_leaves_revenue_unchanged(): void
    {
        $appointment = $this->createWalkIn();
        $before = $this->revenue->sumBetween();

        $this->transition($appointment->fresh(), AppointmentStatus::Cancelled);

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        // Cash collection row is preserved (collection ≠ recognition).
        $this->assertSame(PaymentStatus::Verified, $appointment->payment->status);
        $this->assertEquals($before, $this->revenue->sumBetween());
        $this->assertEquals(0.0, $this->revenue->amountFor($appointment));
    }

    public function test_cannot_cancel_completed_appointment(): void
    {
        $appointment = $this->createWalkIn();
        $this->transition($appointment->fresh(), AppointmentStatus::InService);
        $this->transition($appointment->fresh(), AppointmentStatus::Completed);

        $afterComplete = $this->revenue->sumBetween();

        try {
            $this->transition($appointment->fresh(), AppointmentStatus::Cancelled);
            $this->fail('Expected invalid transition from Completed to Cancelled');
        } catch (\Illuminate\Validation\ValidationException) {
            // expected — reversal would be a separate auditable workflow
        }

        $this->assertEquals($afterComplete, $this->revenue->sumBetween());
        $this->assertSame(AppointmentStatus::Completed, $appointment->fresh()->status);
    }

    public function test_concurrent_completion_requests_count_revenue_once(): void
    {
        $appointment = $this->createWalkIn();
        $this->transition($appointment->fresh(), AppointmentStatus::InService);
        $before = $this->revenue->sumBetween();

        $service = app(AppointmentService::class);
        $id = $appointment->id;

        DB::transaction(function () use ($service, $id) {
            $locked = Appointment::whereKey($id)->lockForUpdate()->firstOrFail();
            $service->transition($locked, AppointmentStatus::Completed, $this->manager);
        });

        try {
            $service->transition(Appointment::findOrFail($id), AppointmentStatus::Completed, $this->manager);
            $this->fail('Second completion should fail');
        } catch (\Illuminate\Validation\ValidationException) {
            // expected
        }

        $this->assertEquals(round($before + 500, 2), $this->revenue->sumBetween());
        $this->assertSame(1, Appointment::where('status', AppointmentStatus::Completed)->whereKey($id)->count());
    }

    public function test_daily_and_monthly_revenue_only_count_completed_services(): void
    {
        $walkIn = $this->createWalkIn();

        // Booked + verified payment must not appear in monthly recognized revenue.
        $this->assertEquals(0.0, $this->revenue->sumForMonth((int) now()->year, (int) now()->month));

        Appointment::create([
            'user_id' => null,
            'walk_in_name' => 'Cancelled Guest',
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'booked_price' => 500,
            'appointment_date' => now()->toDateString(),
            'start_time' => '12:00',
            'status' => AppointmentStatus::Cancelled,
        ])->payment()->create([
            'amount' => 500,
            'method' => 'cash',
            'status' => PaymentStatus::Verified,
            'verified_at' => now(),
            'verified_by' => $this->manager->id,
        ]);

        $this->assertEquals(0.0, $this->revenue->sumForMonth((int) now()->year, (int) now()->month));

        $this->transition($walkIn->fresh(), AppointmentStatus::InService);
        $this->transition($walkIn->fresh(), AppointmentStatus::Completed);

        $this->assertEquals(500.0, $this->revenue->sumForMonth((int) now()->year, (int) now()->month));
        $this->assertEquals(500.0, $this->revenue->sumBetween(now()->startOfDay(), now()->endOfDay()));
    }

    public function test_dashboard_month_revenue_uses_recognized_not_verified_payments(): void
    {
        $this->createWalkIn(); // verified ₱500, Booked → must not show on dashboard

        $this->actingAs($this->manager)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->assertEquals(0.0, $this->revenue->sumForMonth((int) now()->year, (int) now()->month));

        $appointment = Appointment::where('walk_in_name', 'Walk-in Guest')->firstOrFail();
        $this->transition($appointment->fresh(), AppointmentStatus::InService);
        $this->transition($appointment->fresh(), AppointmentStatus::Completed);

        $this->assertEquals(500.0, $this->revenue->sumForMonth((int) now()->year, (int) now()->month));

        $this->actingAs($this->manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('₱500.00', false);
    }

    public function test_online_booking_shares_same_recognition_rules(): void
    {
        $client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $appointment = Appointment::create([
            'user_id' => $client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'booked_price' => 500,
            'appointment_date' => now()->toDateString(),
            'start_time' => '13:00',
            'status' => AppointmentStatus::Unverified,
        ]);
        Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => 500,
            'method' => 'gcash',
            'status' => PaymentStatus::Pending,
        ]);

        $before = $this->revenue->sumBetween();

        $this->transition($appointment->fresh(), AppointmentStatus::Booked);
        $this->assertEquals($before, $this->revenue->sumBetween());

        $this->transition($appointment->fresh(), AppointmentStatus::InService);
        $this->transition($appointment->fresh(), AppointmentStatus::Completed);

        $this->assertEquals(round($before + 500, 2), $this->revenue->sumBetween());
    }

    public function test_revenue_reconcile_command_is_read_only(): void
    {
        $this->createWalkIn();
        $paymentsBefore = Payment::count();

        $this->artisan('revenue:reconcile')
            ->assertSuccessful()
            ->expectsOutputToContain('No data was modified');

        $this->assertSame($paymentsBefore, Payment::count());
    }
}
