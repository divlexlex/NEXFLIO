<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPortalTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $owner;
    private User $manager;
    private User $staff;
    private User $client;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::where('email', 'owner@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();

        $this->service = Service::create([
            'name' => 'Foot Spa',
            'category' => 'Massage',
            'description' => 'Test',
            'price' => 600.00,
            'duration_minutes' => 45,
            'status' => 'active',
        ]);
    }

    public function test_landing_page_renders_with_services(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Perfect')
            ->assertSee('Foot Spa');
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Management Portal');
    }

    public function test_staff_and_clients_cannot_log_into_the_portal(): void
    {
        foreach ([$this->staff, $this->client] as $user) {
            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

            $response->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    public function test_manager_can_log_in_and_reach_the_dashboard(): void
    {
        $this->post('/login', [
            'email' => $this->manager->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->actingAs($this->manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }

    public function test_staff_and_clients_get_403_on_admin_pages(): void
    {
        $this->actingAs($this->staff)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($this->client)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_audit_trail_is_owner_only(): void
    {
        $this->actingAs($this->manager)->get('/admin/audit-logs')->assertForbidden();
        $this->actingAs($this->owner)->get('/admin/audit-logs')->assertOk();

        $this->actingAs($this->manager)->get('/admin/reports/financial')->assertForbidden();
        $this->actingAs($this->owner)->get('/admin/reports/financial')->assertOk();
    }

    public function test_walk_in_creates_booked_appointment_with_verified_cash_payment(): void
    {
        $response = $this->actingAs($this->manager)->post('/admin/appointments/walk-in', [
            'walk_in_name' => 'Maria Santos',
            'walk_in_phone' => '0917 000 0000',
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '11:00',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $appointment = Appointment::where('walk_in_name', 'Maria Santos')->firstOrFail();
        $this->assertNull($appointment->user_id);
        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertSame('cash', $appointment->payment->method);
        $this->assertSame(PaymentStatus::Verified, $appointment->payment->status);
        $this->assertSame($this->manager->id, $appointment->payment->verified_by);
        $this->assertSame('600.00', (string) $appointment->payment->amount);
    }

    public function test_web_payment_verification_matches_api_side_effects(): void
    {
        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay(),
            'start_time' => '10:00:00',
            'status' => AppointmentStatus::Unverified,
        ]);
        $payment = $appointment->payment()->create([
            'amount' => $this->service->price,
            'method' => 'gcash',
            'proof_path' => 'payment_proofs/test.jpg',
            'status' => PaymentStatus::Pending,
        ]);

        $this->actingAs($this->manager)
            ->patch("/admin/payments/{$payment->id}/verify")
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertSame(PaymentStatus::Verified, $appointment->payment->status);
        $this->assertSame($this->manager->id, $appointment->payment->verified_by);
    }

    public function test_web_payment_rejection_requires_reason_and_cancels(): void
    {
        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay(),
            'start_time' => '10:00:00',
            'status' => AppointmentStatus::Unverified,
        ]);
        $payment = $appointment->payment()->create([
            'amount' => $this->service->price,
            'method' => 'gcash',
            'proof_path' => 'payment_proofs/test.jpg',
            'status' => PaymentStatus::Pending,
        ]);

        $this->actingAs($this->manager)
            ->patch("/admin/payments/{$payment->id}/reject", [])
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($this->manager)
            ->patch("/admin/payments/{$payment->id}/reject", [
                'rejection_reason' => 'Blurry screenshot.',
            ])
            ->assertSessionHasNoErrors();

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        $this->assertSame(PaymentStatus::Rejected, $appointment->payment->status);
    }

    public function test_admin_pages_render_for_manager(): void
    {
        foreach (['appointments', 'payments', 'inventory', 'employees', 'leaves', 'billing', 'services'] as $page) {
            $this->actingAs($this->manager)->get("/admin/{$page}")->assertOk();
        }
    }
}
