<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Commission;
use App\Models\Position;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Package commissions use each inclusion's ORIGINAL catalog price — a
 * discounted package total never reduces tech pay.
 */
class PackageCommissionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_package_completion_pays_each_tech_ten_percent_of_original_service_price(): void
    {
        $manager = User::where('email', 'manager@nexflio.test')->firstOrFail();

        $manicure = Service::create([
            'name' => 'Nx Manicure',
            'category' => 'Nails',
            'description' => 'Test',
            'price' => 300,
            'duration_minutes' => 45,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);
        $massage = Service::create([
            'name' => 'Nx Massage',
            'category' => 'Massage',
            'description' => 'Test',
            'price' => 700,
            'duration_minutes' => 60,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);

        $nailPosId = (int) Position::where('name', 'Nail Technician')->value('id');
        $massagePosId = (int) Position::where('name', 'Massage Technician')->value('id');

        $package = Service::create([
            'name' => 'Nx Discount Package',
            'category' => 'Packages',
            'description' => 'Test',
            'package_details' => $manicure->name."\n".$massage->name,
            'price' => 900, // discounted — must NOT drive commission
            'duration_minutes' => 105,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);
        $package->positions()->sync([$nailPosId, $massagePosId]);

        $nailTech = $this->makeStaff('Nina', 'Nails', 'Nail Technician');
        $massageTech = $this->makeStaff('Maya', 'Massage', 'Massage Technician');

        $appointment = Appointment::create([
            'user_id' => User::where('email', 'client@nexflio.test')->value('id'),
            'service_id' => $package->id,
            'booked_price' => 900,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '11:00',
            'status' => AppointmentStatus::InService,
        ]);
        $appointment->syncPersonnelAssignments([
            $nailPosId => $nailTech->id,
            $massagePosId => $massageTech->id,
        ]);
        $appointment->payment()->create([
            'amount' => 900,
            'method' => 'cash',
            'status' => PaymentStatus::Verified,
            'verified_by' => $manager->id,
            'verified_at' => now(),
        ]);

        app(AppointmentService::class)->transition(
            $appointment->fresh(),
            AppointmentStatus::Completed,
            $manager
        );

        $rows = Commission::where('appointment_id', $appointment->id)->get();
        $this->assertCount(2, $rows);

        $nailRow = $rows->firstWhere('user_id', $nailTech->id);
        $this->assertNotNull($nailRow);
        $this->assertSame($manicure->id, $nailRow->included_service_id);
        $this->assertSame('300.00', (string) $nailRow->service_price);
        $this->assertSame('30.00', (string) $nailRow->amount);

        $massageRow = $rows->firstWhere('user_id', $massageTech->id);
        $this->assertNotNull($massageRow);
        $this->assertSame($massage->id, $massageRow->included_service_id);
        $this->assertSame('700.00', (string) $massageRow->service_price);
        $this->assertSame('70.00', (string) $massageRow->amount);

        // Idempotent — completing again must not double-pay (status blocks it,
        // and firstOrCreate would also protect a direct re-call).
        $this->assertSame(2, Commission::where('appointment_id', $appointment->id)->count());
    }

    private function makeStaff(string $first, string $last, string $position): User
    {
        $user = User::create([
            'name' => "{$first} {$last}",
            'email' => strtolower("{$first}.{$last}@nexflio.test"),
            'password' => bcrypt('password'),
            'role_id' => User::ROLE_STAFF,
        ]);
        StaffProfile::create([
            'user_id' => $user->id,
            'position' => $position,
            'employment_status' => 'active',
            'commission_rate' => 10,
        ]);

        return $user;
    }
}
