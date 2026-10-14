<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Commission;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * data:fresh-start-appointments wipes every appointment, payment, and
 * commission in one transaction while leaving every user account intact.
 * The mysqldump backup is skipped here (--no-backup): the test database
 * is sqlite, where mysqldump cannot work.
 */
class FreshStartAppointmentsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_fresh_start_wipes_appointments_but_keeps_users(): void
    {
        $owner = User::where('email', 'client@nexflio.test')->firstOrFail();
        $other = User::create([
            'name' => 'Second Client', 'email' => 'client-b@nexflio.test',
            'password' => bcrypt('password'), 'role_id' => User::ROLE_CLIENT,
        ]);
        $service = Service::create([
            'name' => 'Wipe Me (test)', 'category' => 'Nails', 'description' => 'Test',
            'price' => 179, 'duration_minutes' => 45, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        foreach ([$owner, $other] as $user) {
            $appointment = Appointment::create([
                'user_id' => $user->id,
                'service_id' => $service->id,
                'personnel_id' => null,
                'appointment_date' => now()->addDay()->toDateString(),
                'start_time' => '10:00',
                'status' => AppointmentStatus::Booked,
            ]);
            $appointment->payment()->create([
                'amount' => 179,
                'method' => 'gcash',
                'status' => PaymentStatus::Verified,
                'verified_at' => now(),
            ]);
            Commission::create([
                'user_id' => $user->id,
                'appointment_id' => $appointment->id,
                'service_price' => 179,
                'rate' => 10,
                'amount' => 17.90,
                'earned_at' => now(),
            ]);
        }

        $this->assertSame(2, Appointment::count());
        $this->assertSame(2, Payment::count());
        $this->assertSame(2, Commission::count());

        $this->artisan('data:fresh-start-appointments', ['--no-backup' => true])
            ->assertSuccessful();

        $this->assertSame(0, Appointment::withTrashed()->count());
        $this->assertSame(0, Payment::withTrashed()->count());
        $this->assertSame(0, Commission::count());

        $this->assertNotNull(User::find($owner->id));
        $this->assertNotNull(User::find($other->id));
        $this->assertSame('Second Client', User::find($other->id)->name);
    }
}
