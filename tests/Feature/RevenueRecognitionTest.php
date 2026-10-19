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
 * Rule A (sha, 2026-10-19): revenue = Verified payments, dated by
 * verified_at. A down payment credits revenue the moment it is verified,
 * including a second-chance payment after a rejection (top-up / resubmit).
 * Payments on Cancelled appointments count ₱0; completion adds nothing
 * (the branch settlement is already a Verified payment), so no total can
 * ever double-count.
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

        $this->travelTo(now()->addDay()->setTime(8, 0));

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
            'start_time' => '12:00',
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
        // Walk-ins now start with NO payment — the booking has a balance but
        // no revenue until a payment is recorded and verified.
        $this->assertNull($appointment->payment);
        $this->assertSame(0.0, $appointment->verifiedPaid());
        $this->assertSame(500.0, $appointment->balanceDue());
        $this->assertNull($appointment->completed_at);
        $this->assertEquals($before, $this->revenue->sumBetween());
        $this->assertEquals(0.0, $this->revenue->amountFor($appointment->fresh()));
    }

    public function test_verify_credits_revenue_and_start_adds_nothing(): void
    {
        // Rule A: Unverified → Booked (verify) credits the verified payment
        // at once; Booked → InService adds nothing more.
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
        $this->assertEquals(round($before + 500, 2), $this->revenue->sumBetween());

        $this->transition($appointment->fresh(), AppointmentStatus::InService);
        $this->assertEquals(round($before + 500, 2), $this->revenue->sumBetween());
        $this->assertNull($appointment->fresh()->completed_at);
    }

    public function test_completing_walk_in_does_not_double_count_revenue(): void
    {
        $appointment = $this->createWalkIn();

        // The branch records the ₱500 at the counter (an immediately
        // Verified kind='branch' payment) — that recording IS the credit.
        $appointment->payments()->create([
            'kind' => 'branch',
            'amount' => 500,
            'method' => 'cash',
            'status' => PaymentStatus::Verified,
            'verified_by' => $this->manager->id,
            'verified_at' => now(),
        ]);

        $before = $this->revenue->sumBetween();
        $this->assertEquals(500.0, $this->revenue->amountFor($appointment->fresh()));

        $this->transition($appointment->fresh(), AppointmentStatus::InService);
        $this->assertEquals($before, $this->revenue->sumBetween());

        $this->transition($appointment->fresh(), AppointmentStatus::Completed);

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Completed, $appointment->status);
        $this->assertNotNull($appointment->completed_at);
        // Completion adds nothing — the total stays exactly the ₱500 paid.
        $this->assertEquals($before, $this->revenue->sumBetween());
        $this->assertEquals(500.0, $this->revenue->amountFor($appointment));

        // Second completion is rejected — still no double count.
        try {
            $this->transition($appointment->fresh(), AppointmentStatus::Completed);
            $this->fail('Expected invalid transition from Completed to Completed');
        } catch (\Illuminate\Validation\ValidationException) {
            // expected
        }

        $this->assertEquals($before, $this->revenue->sumBetween());
    }

    public function test_cancelling_pending_or_confirmed_walk_in_leaves_revenue_unchanged(): void
    {
        $appointment = $this->createWalkIn();
        $before = $this->revenue->sumBetween();

        $this->transition($appointment->fresh(), AppointmentStatus::Cancelled);

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        // No money was ever collected — walk-ins start unpaid — and cancelling
        // still recognizes nothing.
        $this->assertNull($appointment->payment);
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
        $appointment->payments()->create([
            'kind' => 'branch',
            'amount' => 500,
            'method' => 'cash',
            'status' => PaymentStatus::Verified,
            'verified_by' => $this->manager->id,
            'verified_at' => now(),
        ]);
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

        $this->assertEquals($before, $this->revenue->sumBetween());
        $this->assertSame(1, Appointment::where('status', AppointmentStatus::Completed)->whereKey($id)->count());
    }

    public function test_daily_and_monthly_revenue_count_verified_payments_only(): void
    {
        $walkIn = $this->createWalkIn();

        // No payment recorded yet → nothing counts.
        $this->assertEquals(0.0, $this->revenue->sumForMonth((int) now()->year, (int) now()->month));

        // A Verified payment on a CANCELLED appointment counts ₱0.
        Appointment::create([
            'user_id' => null,
            'walk_in_name' => 'Cancelled Guest',
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'booked_price' => 500,
            'appointment_date' => now()->toDateString(),
            'start_time' => '14:00',
            'status' => AppointmentStatus::Cancelled,
        ])->payment()->create([
            'amount' => 500,
            'method' => 'cash',
            'status' => PaymentStatus::Verified,
            'verified_at' => now(),
            'verified_by' => $this->manager->id,
        ]);

        $this->assertEquals(0.0, $this->revenue->sumForMonth((int) now()->year, (int) now()->month));

        // Recording the walk-in's branch payment credits ₱500 at once,
        // dated by verified_at (today) — completion afterwards adds ₱0.
        $walkIn->payments()->create([
            'kind' => 'branch',
            'amount' => 500,
            'method' => 'cash',
            'status' => PaymentStatus::Verified,
            'verified_at' => now(),
            'verified_by' => $this->manager->id,
        ]);

        $this->assertEquals(500.0, $this->revenue->sumForMonth((int) now()->year, (int) now()->month));
        $this->assertEquals(500.0, $this->revenue->sumBetween(now()->startOfDay(), now()->endOfDay()));

        $this->transition($walkIn->fresh(), AppointmentStatus::InService);
        $this->transition($walkIn->fresh(), AppointmentStatus::Completed);

        $this->assertEquals(500.0, $this->revenue->sumForMonth((int) now()->year, (int) now()->month));
    }

    public function test_dashboard_month_revenue_uses_verified_payments(): void
    {
        $appointment = $this->createWalkIn(); // no payment yet → ₱0 revenue

        $this->assertEquals(0.0, $this->revenue->sumForMonth((int) now()->year, (int) now()->month));

        // Recording the counter payment credits the dashboard at once.
        $appointment->payments()->create([
            'kind' => 'branch',
            'amount' => 500,
            'method' => 'cash',
            'status' => PaymentStatus::Verified,
            'verified_by' => $this->manager->id,
            'verified_at' => now(),
        ]);

        $this->assertEquals(500.0, $this->revenue->sumForMonth((int) now()->year, (int) now()->month));

        $this->actingAs($this->manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('₱500.00', false);

        // Completion afterwards changes nothing.
        $this->transition($appointment->fresh(), AppointmentStatus::InService);
        $this->transition($appointment->fresh(), AppointmentStatus::Completed);

        $this->assertEquals(500.0, $this->revenue->sumForMonth((int) now()->year, (int) now()->month));
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

        // Verify credits at once; starting and completing add nothing.
        $this->transition($appointment->fresh(), AppointmentStatus::Booked);
        $this->assertEquals(round($before + 500, 2), $this->revenue->sumBetween());

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
