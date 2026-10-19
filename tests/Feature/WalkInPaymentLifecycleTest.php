<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\AppointmentAddedService;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Walk-in payment lifecycle. Walk-ins are created Booked with NO payment
 * (the full price is the balance); the manager records collection from the
 * Payments page, only a settled balance reveals check-out, and adding a
 * service reopens the balance until it is settled again. Also covers the
 * Payments Source filter (walk-ins vs online) and its session persistence.
 */
class WalkInPaymentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;

    private User $client;

    private User $massageStaff;

    private User $facialStaff;

    private Service $massage;

    private Service $facial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->massageStaff = $this->makeStaff('Massage Technician', 'lifecycle.massage@nexflio.test');
        $this->facialStaff = $this->makeStaff('Facial Technician', 'lifecycle.facial@nexflio.test');

        $this->massage = $this->service('Lifecycle Massage', 'Massage', 500);
        $this->facial = $this->service('Lifecycle Facial', 'Facial', 300);
    }

    private function makeStaff(string $position, string $email): User
    {
        $user = User::create([
            'name' => ucfirst(explode(' ', $position)[0]).' Lifecycle',
            'email' => $email,
            'role_id' => User::ROLE_STAFF,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        StaffProfile::create([
            'user_id' => $user->id,
            'base_pay' => 12000,
            'commission_rate' => 10.00,
            'position' => $position,
            'employment_status' => 'active',
            'hired_at' => now()->toDateString(),
        ]);

        return $user;
    }

    private function service(string $name, string $category, float $price, int $duration = 60): Service
    {
        return Service::create([
            'name' => $name,
            'category' => $category,
            'description' => 'Test service',
            'price' => $price,
            'duration_minutes' => $duration,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);
    }

    private function createWalkIn(string $name): Appointment
    {
        $this->actingAs($this->manager)
            ->post(route('admin.appointments.walk-in'), [
                'walk_in_name' => $name,
                'service_id' => $this->massage->id,
                'personnel_id' => $this->massageStaff->id,
                'appointment_date' => now()->toDateString(),
                'start_time' => '15:00',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return Appointment::where('walk_in_name', $name)->latest('id')->firstOrFail();
    }

    /** A walk-in with optional extra-service rows (main is always $this->massage). */
    private function postWalkInWithExtras(string $name, array $extras, string $startTime = '15:00')
    {
        return $this->actingAs($this->manager)
            ->post(route('admin.appointments.walk-in'), [
                'walk_in_name' => $name,
                'service_id' => $this->massage->id,
                'personnel_id' => $this->massageStaff->id,
                'appointment_date' => now()->toDateString(),
                'start_time' => $startTime,
                'extras' => $extras,
            ]);
    }

    /** The All tab shows both paid and as-yet-unpaid active cards. */
    private function paymentsHtml(): string
    {
        return $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all']))
            ->assertOk()
            ->getContent();
    }

    public function test_walk_in_creation_has_no_payment_and_full_balance(): void
    {
        $appointment = $this->createWalkIn('Fresh Guest');

        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertNull($appointment->payment);
        $this->assertSame(0, $appointment->payments()->count());
        $this->assertSame(0.0, $appointment->verifiedPaid());
        $this->assertSame(500.0, $appointment->balanceDue());
    }

    public function test_settle_reopen_on_added_service_then_checkout_pays_commission(): void
    {
        $appointment = $this->createWalkIn('Lifecycle Guest');

        // Balance due: Record form shown, check-out hidden.
        $html = $this->paymentsHtml();
        $this->assertStringContainsString('id="settle-form-'.$appointment->id.'"', $html);
        $this->assertStringNotContainsString('Confirm check-out', $html);

        // Record the full branch payment.
        $this->actingAs($this->manager)
            ->post(route('admin.appointments.branch-payment', $appointment->id), ['amount' => 500, 'method' => 'cash'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0.0, $appointment->refresh()->balanceDue());

        // Settled: check-out is revealed, Record form is gone.
        $html = $this->paymentsHtml();
        $this->assertStringContainsString('Confirm check-out', $html);
        $this->assertStringNotContainsString('id="settle-form-'.$appointment->id.'"', $html);

        // Add a service while still Booked → the balance reopens and the card
        // lists it.
        $this->actingAs($this->manager)
            ->post(route('admin.appointments.additional-services.store', $appointment->id), [
                'service_id' => $this->facial->id,
                'personnel_id' => $this->facialStaff->id,
            ])
            ->assertRedirect()->assertSessionHas('success');

        $appointment->refresh();
        $this->assertSame(300.0, $appointment->balanceDue());

        $html = $this->paymentsHtml();
        $this->assertStringContainsString('id="settle-form-'.$appointment->id.'"', $html);
        $this->assertStringNotContainsString('Confirm check-out', $html);
        $this->assertStringContainsString('Lifecycle Facial', $html);

        // Record the reopened balance → check-out returns.
        $this->actingAs($this->manager)
            ->post(route('admin.appointments.branch-payment', $appointment->id), ['amount' => 300, 'method' => 'cash'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0.0, $appointment->refresh()->balanceDue());

        $this->assertStringContainsString('Confirm check-out', $this->paymentsHtml());

        // Confirm check-out completes the visit and pays the added service.
        $this->actingAs($this->manager)
            ->post(route('admin.appointments.checkout', $appointment->id))
            ->assertRedirect()->assertSessionHasNoErrors();

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Completed, $appointment->status);

        $this->assertDatabaseHas('commissions', [
            'appointment_id' => $appointment->id,
            'user_id' => $this->facialStaff->id,
            'included_service_id' => $this->facial->id,
            'amount' => 30.00,
        ]);
    }

    public function test_add_with_down_payment_totals_into_the_record_form_then_checkout(): void
    {
        // Main ₱999 walk-in, entirely unpaid.
        $main = $this->service('Lifecycle Main 999', 'Massage', 999);
        $add = $this->service('Lifecycle Add 599', 'Facial', 599);

        $this->actingAs($this->manager)
            ->post(route('admin.appointments.walk-in'), [
                'walk_in_name' => 'Totals Guest',
                'service_id' => $main->id,
                'personnel_id' => $this->massageStaff->id,
                'appointment_date' => now()->toDateString(),
                'start_time' => '15:00',
            ])->assertSessionHasNoErrors()->assertRedirect();

        $appointment = Appointment::where('walk_in_name', 'Totals Guest')->latest('id')->firstOrFail();
        $this->assertSame(999.0, $appointment->balanceDue());

        // The Record form is prefilled with the current balance; no check-out.
        $html = $this->paymentsHtml();
        $this->assertStringContainsString('id="settle-form-'.$appointment->id.'"', $html);
        $this->assertStringContainsString('value="999.00"', $html);
        $this->assertStringNotContainsString('Confirm check-out', $html);

        // Add ₱599 while STILL Booked, recording a ₱299.50 down payment.
        $this->actingAs($this->manager)
            ->post(route('admin.appointments.additional-services.store', $appointment->id), [
                'service_id' => $add->id,
                'personnel_id' => $this->facialStaff->id,
                'down_payment' => 299.50,
                'method' => 'cash',
            ])->assertRedirect()->assertSessionHas('success');

        // DP ₱299.50 recorded; booked 999 + 599 = 1598; balance 1298.50.
        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointment->id,
            'kind' => 'branch',
            'amount' => 299.50,
            'method' => 'cash',
            'status' => 'verified',
        ]);
        // The later add is a payment-time add, not a creation avail.
        $this->assertDatabaseHas('appointment_added_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $add->id,
            'source' => 'payment',
        ]);
        $appointment->refresh();
        $this->assertSame(1598.0, (float) $appointment->booked_price);
        $this->assertSame(1298.50, $appointment->balanceDue());

        // The Record form now prefills the new total (main + unpaid half).
        $html = $this->paymentsHtml();
        $this->assertStringContainsString('id="settle-form-'.$appointment->id.'"', $html);
        $this->assertStringContainsString('value="1298.50"', $html);
        $this->assertStringNotContainsString('Confirm check-out', $html);

        // Recording the remainder reveals Confirm check-out.
        $this->actingAs($this->manager)
            ->post(route('admin.appointments.branch-payment', $appointment->id), ['amount' => 1298.50, 'method' => 'cash'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0.0, $appointment->refresh()->balanceDue());
        $this->assertStringContainsString('Confirm check-out', $this->paymentsHtml());
    }

    public function test_walk_in_with_add_dp_stays_pending_until_settled(): void
    {
        $add = $this->service('Lifecycle P799', 'Facial', 799);

        $appointment = $this->createWalkIn('Pending Guest'); // main ₱500, Booked, unpaid

        // Add a ₱799 service with a ₱399.50 down payment recorded.
        $this->actingAs($this->manager)
            ->post(route('admin.appointments.additional-services.store', $appointment->id), [
                'service_id' => $add->id,
                'personnel_id' => $this->facialStaff->id,
                'down_payment' => 399.50,
                'method' => 'cash',
            ])->assertRedirect()->assertSessionHas('success');

        $appointment->refresh();
        $this->assertSame(899.50, $appointment->balanceDue()); // 500 + 799 − 399.50

        // Fix A: the added row shows its own recorded down payment amount.
        $pending = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'pending']))
            ->assertOk()->getContent();
        $this->assertStringContainsString($appointment->referenceCode(), $pending);
        $this->assertStringContainsString('Down payment: ₱399.50', $pending);

        // Fix B: a recorded DP does NOT move a walk-in into Verified — it
        // stays Pending while a balance remains.
        $verified = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'verified']))
            ->assertOk()->getContent();
        $this->assertStringNotContainsString($appointment->referenceCode(), $verified);

        // Settle the remaining balance → the card moves to Verified.
        $this->actingAs($this->manager)
            ->post(route('admin.appointments.branch-payment', $appointment->id), ['amount' => 899.50, 'method' => 'cash'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0.0, $appointment->refresh()->balanceDue());

        $pendingAfter = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'pending']))
            ->assertOk()->getContent();
        $this->assertStringNotContainsString($appointment->referenceCode(), $pendingAfter);

        $verifiedAfter = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'verified']))
            ->assertOk()->getContent();
        $this->assertStringContainsString($appointment->referenceCode(), $verifiedAfter);
    }

    public function test_source_filter_lists_walk_ins_or_online_and_persists(): void
    {
        $walkIn = $this->createWalkIn('Source Guest');

        $online = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->facial->id,
            'personnel_id' => $this->facialStaff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'booked_price' => 300,
            'status' => AppointmentStatus::Unverified,
        ]);
        $online->payment()->create([
            'amount' => 300,
            'method' => 'gcash',
            'status' => PaymentStatus::Pending,
        ]);

        $walkInHtml = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all', 'source' => 'walkin']))
            ->assertOk()->getContent();
        $this->assertStringContainsString($walkIn->referenceCode(), $walkInHtml);
        $this->assertStringNotContainsString($online->referenceCode(), $walkInHtml);

        $onlineHtml = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all', 'source' => 'online']))
            ->assertOk()->getContent();
        $this->assertStringContainsString($online->referenceCode(), $onlineHtml);
        $this->assertStringNotContainsString($walkIn->referenceCode(), $onlineHtml);

        // Choosing Walk-ins stores the choice; a param-less revisit keeps it.
        $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all', 'source' => 'walkin']))
            ->assertOk();

        $persisted = $this->actingAs($this->manager)
            ->get(route('admin.payments', ['status' => 'all']))
            ->assertOk()->getContent();
        $this->assertStringContainsString($walkIn->referenceCode(), $persisted);
        $this->assertStringNotContainsString($online->referenceCode(), $persisted);
    }

    public function test_walk_in_with_extras_sums_prices_and_creates_rows_with_no_payment(): void
    {
        $variantService = $this->service('Lifecycle Variant', 'Facial', 700);
        $variantService->variants()->create(['label' => 'Solo', 'price' => 600, 'guests' => 1, 'sort' => 0]);

        $this->postWalkInWithExtras('Combo Guest', [
            ['service_id' => $this->facial->id, 'personnel_id' => $this->facialStaff->id], // 300, no variant
            ['service_id' => $variantService->id, 'variant_label' => 'Solo'],             // 600 via variant
        ])->assertSessionHasNoErrors()->assertRedirect();

        $appointment = Appointment::where('walk_in_name', 'Combo Guest')->latest('id')->firstOrFail();

        // 500 main + 300 + 600; no payment collected at creation.
        $this->assertSame(1400.0, (float) $appointment->booked_price);
        $this->assertSame(1400.0, $appointment->balanceDue());
        $this->assertSame(0, $appointment->payments()->count());
        $this->assertSame(2, $appointment->addedServices()->count());

        $this->assertDatabaseHas('appointment_added_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $this->facial->id,
            'variant_label' => null,
            'price' => 300.00,
            'personnel_id' => $this->facialStaff->id,
            'added_by' => $this->manager->id,
            'source' => 'creation',
        ]);
        $this->assertDatabaseHas('appointment_added_services', [
            'appointment_id' => $appointment->id,
            'service_id' => $variantService->id,
            'variant_label' => 'Solo',
            'price' => 600.00,
            'personnel_id' => null,
            'source' => 'creation',
        ]);
    }

    public function test_walk_in_extra_with_ineligible_staff_is_refused(): void
    {
        $this->postWalkInWithExtras('Bad Staff Guest', [
            // A Massage Technician cannot perform a Facial-category service.
            ['service_id' => $this->facial->id, 'personnel_id' => $this->massageStaff->id],
        ])->assertSessionHasErrors();

        $this->assertSame(0, Appointment::where('walk_in_name', 'Bad Staff Guest')->count());
        $this->assertSame(0, AppointmentAddedService::count());
    }

    public function test_walk_in_extras_pushing_past_closing_are_refused_but_shorter_ones_succeed(): void
    {
        $longExtra = $this->service('Lifecycle Long Extra', 'Facial', 100, 60);
        $shortExtra = $this->service('Lifecycle Short Extra', 'Facial', 100, 30);

        // Main 60 + 60 = 120 min from 23:30 → ends 01:30, past the 01:00 close.
        $this->postWalkInWithExtras('Too Late Guest', [
            ['service_id' => $longExtra->id],
        ], '23:30')->assertSessionHasErrors('start_time');
        $this->assertSame(0, Appointment::where('walk_in_name', 'Too Late Guest')->count());

        // Main 60 + 30 = 90 min from 23:30 → ends exactly at the 01:00 close.
        $this->postWalkInWithExtras('Just In Time Guest', [
            ['service_id' => $shortExtra->id],
        ], '23:30')->assertSessionHasNoErrors()->assertRedirect();

        $appointment = Appointment::where('walk_in_name', 'Just In Time Guest')->firstOrFail();
        $this->assertSame(600.0, (float) $appointment->booked_price); // 500 + 100
        $this->assertSame(1, $appointment->addedServices()->count());
    }
}
