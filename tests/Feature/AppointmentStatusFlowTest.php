<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppointmentStatusFlowTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;
    private User $staff;
    private User $otherStaff;
    private User $client;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();

        $this->otherStaff = User::create([
            'name' => 'Other Staff',
            'email' => 'staff2@nexflio.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_STAFF,
        ]);

        $this->service = Service::create([
            'name' => 'Classic Manicure',
            'category' => 'Nails',
            'description' => 'Test service',
            'price' => 500.00,
            'duration_minutes' => 45,
            'status' => 'active',
        ]);
    }

    private function bookAppointment(): Appointment
    {
        Storage::fake('public');

        $response = $this->actingAs($this->client, 'sanctum')->postJson('/api/appointments', [
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ]);

        $response->assertSuccessful();

        return Appointment::findOrFail($response->json('id'));
    }

    public function test_booking_starts_unverified_with_pending_payment_at_service_price(): void
    {
        $appointment = $this->bookAppointment();

        $this->assertSame(AppointmentStatus::Unverified, $appointment->status);
        $this->assertNotNull($appointment->payment);
        $this->assertSame(PaymentStatus::Pending, $appointment->payment->status);
        $this->assertSame('500.00', (string) $appointment->payment->amount);
        Storage::disk('public')->assertExists($appointment->payment->proof_path);
    }

    public function test_staff_cannot_verify_a_booking(): void
    {
        $appointment = $this->bookAppointment();

        $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'booked'])
            ->assertForbidden();
    }

    public function test_manager_approval_books_appointment_and_verifies_payment(): void
    {
        $appointment = $this->bookAppointment();

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'booked'])
            ->assertOk();

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertSame(PaymentStatus::Verified, $appointment->payment->status);
        $this->assertSame($this->manager->id, $appointment->payment->verified_by);
        $this->assertNotNull($appointment->payment->verified_at);
    }

    public function test_rejection_requires_a_reason_and_marks_payment_rejected(): void
    {
        $appointment = $this->bookAppointment();

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'cancelled'])
            ->assertStatus(422);

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", [
                'status' => 'cancelled',
                'rejection_reason' => 'Proof image is unreadable.',
            ])
            ->assertOk();

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        $this->assertSame(PaymentStatus::Rejected, $appointment->payment->status);
        $this->assertSame('Proof image is unreadable.', $appointment->payment->rejection_reason);
    }

    public function test_forbidden_transitions_are_rejected(): void
    {
        $appointment = $this->bookAppointment();

        // unverified cannot jump straight to completed or in-service
        foreach (['completed', 'in-service', 'no-show'] as $status) {
            $this->actingAs($this->manager, 'sanctum')
                ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => $status])
                ->assertStatus(422);
        }

        // garbage statuses fail validation
        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'whatever'])
            ->assertStatus(422);
    }

    public function test_assigned_staff_can_run_the_service_lifecycle(): void
    {
        $appointment = $this->bookAppointment();
        $appointment->update(['status' => AppointmentStatus::Booked]);

        $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'in-service'])
            ->assertOk();

        $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'completed'])
            ->assertOk();

        $this->assertSame(AppointmentStatus::Completed, $appointment->fresh()->status);
    }

    public function test_unassigned_staff_cannot_touch_the_appointment(): void
    {
        $appointment = $this->bookAppointment();
        $appointment->update(['status' => AppointmentStatus::Booked]);

        $this->actingAs($this->otherStaff, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'in-service'])
            ->assertForbidden();
    }

    public function test_terminal_statuses_cannot_move(): void
    {
        $appointment = $this->bookAppointment();
        $appointment->update(['status' => AppointmentStatus::Completed]);

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", ['status' => 'booked'])
            ->assertStatus(422);
    }

    public function test_double_booking_same_slot_is_rejected(): void
    {
        Storage::fake('public');
        $this->bookAppointment();

        $this->actingAs($this->client, 'sanctum')->postJson('/api/appointments', [
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'payment_proof' => UploadedFile::fake()->image('proof2.jpg'),
        ])->assertStatus(422);
    }

    public function test_client_only_sees_own_appointments(): void
    {
        $this->bookAppointment();

        $otherClient = User::create([
            'name' => 'Other Client',
            'email' => 'client9@nexflio.test',
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_CLIENT,
        ]);

        $response = $this->actingAs($otherClient, 'sanctum')->getJson('/api/appointments');
        $response->assertOk();
        $this->assertCount(0, $response->json());
    }
}
