<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BusinessHours;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Shop closes at 10:00 PM. Same-day (and any-day) bookings must finish by
 * closing — starts at/after 10:00 PM are refused; customers pick another day.
 */
class BookingClosingCutoffTest extends TestCase
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
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->service = Service::create([
            'name' => 'Cutoff Facial',
            'category' => 'Facial',
            'price' => 500,
            'duration_minutes' => 60,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);
    }

    public function test_start_fits_rejects_times_that_run_past_10pm(): void
    {
        $date = now()->addDay()->toDateString();

        $this->assertTrue(BusinessHours::startFitsOnDate($date, '21:00', 60));
        $this->assertFalse(BusinessHours::startFitsOnDate($date, '21:30', 60));
        $this->assertFalse(BusinessHours::startFitsOnDate($date, '22:00', 60));
        $this->assertFalse(BusinessHours::startFitsOnDate($date, '23:00', 30));
    }

    public function test_branch_store_rejects_start_past_closing(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client)->post(route('account.booking.branch.store'), [
            'service_id' => $this->service->id,
            'personnel_id' => (string) $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '22:00',
            'method' => 'gcash',
            'guests' => 1,
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertSessionHasErrors('start_time');

        $this->assertSame(0, Appointment::count());
    }

    public function test_walk_in_rejects_start_past_walk_in_closing_for_today(): void
    {
        // Walk-ins use config booking.walk_in.close_at (default 01:00), not
        // the 10:00 PM shop close. 00:30 + 60 min = 01:30 → past walk-in close.
        config(['booking.walk_in.close_at' => '01:00']);

        $this->actingAs($this->manager)->post(route('admin.appointments.walk-in'), [
            'walk_in_name' => 'Late Guest',
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->toDateString(),
            'start_time' => '00:30',
        ])->assertSessionHasErrors('start_time');

        $this->assertSame(0, Appointment::count());
    }

    public function test_api_store_rejects_start_past_closing(): void
    {
        Storage::fake('public');

        $this->actingAs($this->client, 'sanctum')->postJson('/api/appointments', [
            'service_id' => $this->service->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '23:00',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertStatus(422)->assertJsonValidationErrors('start_time');
    }
}
