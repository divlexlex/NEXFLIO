<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Commission;
use App\Models\Payment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payments page Check-Out — settle the balance and complete the visit in
 * one step. Money half mirrors branch settlement; status half reuses
 * AppointmentService::transition, so side effects match a normal completion.
 */
class AppointmentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    private User $manager;

    private User $staff;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();

        $this->staff = User::create([
            'name' => 'Checkout Staff',
            'email' => 'checkout.staff@nexflio.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_STAFF,
            'email_verified_at' => now(),
        ]);
        StaffProfile::create([
            'user_id' => $this->staff->id,
            'position' => 'Massage Technician',
            'employment_status' => 'active',
            'commission_rate' => 10,
        ]);

        $this->service = Service::create([
            'name' => 'Checkout Massage (test)', 'category' => 'Massage', 'description' => 'Test',
            'price' => 599, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
    }

    private function makeAppointment(AppointmentStatus $status, float $bookedPrice = 599): Appointment
    {
        return Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->toDateString(),
            'start_time' => '10:00',
            'status' => $status,
            'booked_price' => $bookedPrice,
        ]);
    }

    private function makeVerifiedOnline(Appointment $appointment, float $amount): Payment
    {
        return Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => $amount,
            'method' => 'gcash',
            'status' => PaymentStatus::Verified,
            'verified_by' => $this->manager->id,
            'verified_at' => now(),
        ]);
    }

    public function test_checkout_booked_with_balance_collects_and_completes(): void
    {
        $appointment = $this->makeAppointment(AppointmentStatus::Booked);
        $this->makeVerifiedOnline($appointment, 300);

        $this->actingAs($this->manager)->post(
            route('admin.appointments.checkout', $appointment->id),
            ['amount' => 299, 'method' => 'cash']
        )->assertRedirect()->assertSessionHasNoErrors();

        $appointment->refresh()->load('payments');

        $branch = $appointment->payments()->where('kind', 'branch')->firstOrFail();
        $this->assertEquals(299, (float) $branch->amount);
        $this->assertSame('cash', $branch->method);
        $this->assertSame(PaymentStatus::Verified, $branch->status);
        $this->assertSame($this->manager->id, $branch->verified_by);
        $this->assertNotNull($branch->verified_at);

        $this->assertSame(AppointmentStatus::Completed, $appointment->status);
        $this->assertSame(0.0, $appointment->balanceDue());

        // Same side effect as a normal completion: 10% of the 599 price.
        $this->assertDatabaseHas('commissions', [
            'appointment_id' => $appointment->id,
            'user_id' => $this->staff->id,
            'amount' => 59.90,
        ]);
    }

    public function test_checkout_zero_balance_completes_without_payment(): void
    {
        $appointment = $this->makeAppointment(AppointmentStatus::Booked);
        $this->makeVerifiedOnline($appointment, 599);

        $this->actingAs($this->manager)->post(
            route('admin.appointments.checkout', $appointment->id),
            []
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(AppointmentStatus::Completed, $appointment->refresh()->status);
        $this->assertSame(1, $appointment->payments()->count());
    }

    public function test_partial_checkout_completes_with_remaining_balance(): void
    {
        $appointment = $this->makeAppointment(AppointmentStatus::Booked);
        $this->makeVerifiedOnline($appointment, 300);

        $this->actingAs($this->manager)->post(
            route('admin.appointments.checkout', $appointment->id),
            ['amount' => 100, 'method' => 'cash']
        )->assertRedirect()->assertSessionHasNoErrors();

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Completed, $appointment->status);
        $this->assertSame(199.0, $appointment->balanceDue());
    }

    public function test_checkout_completed_with_balance_collects_without_status_change(): void
    {
        $appointment = $this->makeAppointment(AppointmentStatus::Completed);
        $this->makeVerifiedOnline($appointment, 300);

        $this->actingAs($this->manager)->post(
            route('admin.appointments.checkout', $appointment->id),
            ['amount' => 299, 'method' => 'cash']
        )->assertRedirect()->assertSessionHasNoErrors();

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Completed, $appointment->status);
        $this->assertSame(0.0, $appointment->balanceDue());
    }

    public function test_checkout_refused_for_cancelled_no_show_and_unverified(): void
    {
        foreach ([AppointmentStatus::Cancelled, AppointmentStatus::NoShow, AppointmentStatus::Unverified] as $status) {
            $appointment = $this->makeAppointment($status);

            $this->actingAs($this->manager)->post(
                route('admin.appointments.checkout', $appointment->id),
                ['amount' => 100, 'method' => 'cash']
            )->assertSessionHasErrors('checkout');

            $appointment->refresh();
            $this->assertSame($status, $appointment->status);
            $this->assertSame(0, $appointment->payments()->count());
        }
    }

    public function test_checkout_amount_above_balance_rejected(): void
    {
        $appointment = $this->makeAppointment(AppointmentStatus::Booked);
        $this->makeVerifiedOnline($appointment, 300);

        $this->actingAs($this->manager)->post(
            route('admin.appointments.checkout', $appointment->id),
            ['amount' => 99999, 'method' => 'cash']
        )->assertSessionHasErrors('amount');

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertSame(0, $appointment->payments()->where('kind', 'branch')->count());
    }

    public function test_checkout_completion_matches_normal_path_side_effects(): void
    {
        // Normal path: Booked -> In-Service -> Completed via updateStatus.
        $normal = $this->makeAppointment(AppointmentStatus::Booked);
        $this->makeVerifiedOnline($normal, 599);
        $this->actingAs($this->manager)->patch(
            route('admin.appointments.status', $normal->id),
            ['status' => AppointmentStatus::InService->value]
        )->assertRedirect();
        $this->actingAs($this->manager)->patch(
            route('admin.appointments.status', $normal->id),
            ['status' => AppointmentStatus::Completed->value]
        )->assertRedirect();

        // Check-out path on an identical booking.
        $checkout = $this->makeAppointment(AppointmentStatus::Booked);
        $this->makeVerifiedOnline($checkout, 599);
        $this->actingAs($this->manager)->post(
            route('admin.appointments.checkout', $checkout->id),
            []
        )->assertRedirect();

        $normalCommission = Commission::where('appointment_id', $normal->id)->firstOrFail();
        $checkoutCommission = Commission::where('appointment_id', $checkout->id)->firstOrFail();

        $this->assertEquals((float) $normalCommission->amount, (float) $checkoutCommission->amount);
        $this->assertSame($normalCommission->user_id, $checkoutCommission->user_id);
        $this->assertSame(AppointmentStatus::Completed, $checkout->refresh()->status);
    }

    public function test_settled_card_shows_settled_badge(): void
    {
        $appointment = $this->makeAppointment(AppointmentStatus::Completed);
        $this->makeVerifiedOnline($appointment, 599);

        $this->actingAs($this->manager)->get(route('admin.payments', ['status' => 'all']))
            ->assertOk()
            ->assertSee('Settled · Paid in full');
    }
}
