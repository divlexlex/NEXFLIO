<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BusinessHours;
use App\Models\Position;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Packages with multiple catering positions: schedule UI, store sync into
 * appointment_personnel, and admin assign-per-role.
 */
class PackageMultiStaffBookingTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    private User $manager;

    private User $facialStaff;

    private User $massageStaff;

    private Service $package;

    private int $facialPositionId;

    private int $massagePositionId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();

        foreach (range(0, 6) as $day) {
            BusinessHours::updateOrCreate(
                ['day_of_week' => $day],
                ['is_open' => true, 'open_at' => '10:00', 'close_at' => '21:00']
            );
        }

        $this->facialPositionId = (int) Position::where('name', 'Facial Technician')->value('id');
        $this->massagePositionId = (int) Position::where('name', 'Massage Technician')->value('id');

        $this->facialStaff = $this->makeStaff('Faye', 'Facial', 'Facial Technician');
        $this->massageStaff = $this->makeStaff('Maya', 'Massage', 'Massage Technician');

        $this->package = Service::create([
            'name' => 'Nx Multi Staff Package',
            'category' => 'Packages',
            'description' => 'Test',
            'package_details' => "Facial\nMassage",
            'price' => 1500,
            'duration_minutes' => 90,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);
        $this->package->positions()->sync([$this->facialPositionId, $this->massagePositionId]);
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
        ]);

        return $user;
    }

    public function test_schedule_shows_one_picker_per_package_role(): void
    {
        $html = $this->actingAs($this->client)->get(route('account.booking.branch.schedule', [
            'service' => $this->package->id,
            'date' => now()->addDay()->toDateString(),
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('staff['.$this->facialPositionId.']', $html);
        $this->assertStringContainsString('staff['.$this->massagePositionId.']', $html);
        $this->assertStringContainsString('Faye Facial', $html);
        $this->assertStringContainsString('Maya Massage', $html);
        $this->assertStringContainsString('This package needs one staff member per role', $html);
    }

    public function test_client_can_book_package_with_one_staff_per_role(): void
    {
        $date = now()->addDay()->toDateString();

        $this->actingAs($this->client)->post(route('account.booking.branch.store'), [
            'service_id' => $this->package->id,
            'appointment_date' => $date,
            'start_time' => '11:00',
            'staff' => [
                $this->facialPositionId => $this->facialStaff->id,
                $this->massagePositionId => $this->massageStaff->id,
            ],
            'method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $appointment = Appointment::where('service_id', $this->package->id)->firstOrFail();
        $this->assertContains($appointment->personnel_id, [$this->facialStaff->id, $this->massageStaff->id]);
        $this->assertEqualsCanonicalizing(
            [
                $this->facialPositionId => $this->facialStaff->id,
                $this->massagePositionId => $this->massageStaff->id,
            ],
            $appointment->personnelAssignments()->pluck('user_id', 'position_id')->all()
        );
        $this->assertStringContainsString('Facial Technician: Faye Facial', $appointment->personnelDisplayLabel());
        $this->assertStringContainsString('Massage Technician: Maya Massage', $appointment->personnelDisplayLabel());
    }

    public function test_walk_in_single_service_rejects_wrong_category_staff(): void
    {
        $facial = Service::create([
            'name' => 'Nx Walk-in Facial',
            'category' => 'Facial',
            'description' => 'Test',
            'price' => 499,
            'duration_minutes' => 45,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);

        $this->actingAs($this->manager)->post(route('admin.appointments.walk-in'), [
            'walk_in_name' => 'Wrong Category Client',
            'service_id' => $facial->id,
            'personnel_id' => $this->massageStaff->id,
            'appointment_date' => now()->toDateString(),
            'start_time' => '16:00',
        ])->assertSessionHasErrors('personnel_id');

        $this->actingAs($this->manager)->post(route('admin.appointments.walk-in'), [
            'walk_in_name' => 'Correct Category Client',
            'service_id' => $facial->id,
            'personnel_id' => $this->facialStaff->id,
            'appointment_date' => now()->toDateString(),
            'start_time' => '16:00',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('appointments', [
            'walk_in_name' => 'Correct Category Client',
            'personnel_id' => $this->facialStaff->id,
        ]);
    }

    public function test_walk_in_rejects_staff_when_category_has_no_linked_positions(): void
    {
        $this->assertSame([], Position::namesForServiceCategory('Aesthetics'));

        $aesthetic = Service::create([
            'name' => 'Nx Walk-in Gluta',
            'category' => 'Aesthetics',
            'description' => 'Test',
            'price' => 849,
            'duration_minutes' => 45,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);

        $this->actingAs($this->manager)->post(route('admin.appointments.walk-in'), [
            'walk_in_name' => 'No Eligible Staff Client',
            'service_id' => $aesthetic->id,
            'personnel_id' => $this->facialStaff->id,
            'appointment_date' => now()->toDateString(),
            'start_time' => '16:30',
        ])->assertSessionHasErrors('personnel_id');

        $this->assertDatabaseMissing('appointments', [
            'walk_in_name' => 'No Eligible Staff Client',
        ]);
    }

    public function test_walk_in_package_requires_one_staff_per_role(): void
    {
        $this->actingAs($this->manager)->post(route('admin.appointments.walk-in'), [
            'walk_in_name' => 'Walk In Package Client',
            'service_id' => $this->package->id,
            'personnel_id' => $this->facialStaff->id,
            'appointment_date' => now()->toDateString(),
            'start_time' => '15:00',
        ])->assertSessionHasErrors('staff');

        $this->actingAs($this->manager)->post(route('admin.appointments.walk-in'), [
            'walk_in_name' => 'Walk In Package Client',
            'service_id' => $this->package->id,
            'staff' => [
                $this->facialPositionId => $this->facialStaff->id,
                $this->massagePositionId => $this->massageStaff->id,
            ],
            'appointment_date' => now()->toDateString(),
            'start_time' => '15:00',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $appointment = Appointment::where('walk_in_name', 'Walk In Package Client')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [
                $this->facialPositionId => $this->facialStaff->id,
                $this->massagePositionId => $this->massageStaff->id,
            ],
            $appointment->personnelAssignments()->pluck('user_id', 'position_id')->all()
        );
    }

    public function test_admin_can_assign_staff_per_package_role(): void
    {
        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $this->package->id,
            'booked_price' => 1500,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '14:00',
            'status' => 'booked',
        ]);
        $appointment->syncPersonnelAssignments([
            $this->facialPositionId => null,
            $this->massagePositionId => null,
        ]);

        $this->actingAs($this->manager)->patch(
            route('admin.appointments.assign-personnel', $appointment->id),
            [
                'staff' => [
                    $this->facialPositionId => $this->facialStaff->id,
                    $this->massagePositionId => $this->massageStaff->id,
                ],
            ]
        )->assertRedirect()->assertSessionHasNoErrors();

        $appointment->refresh();
        $this->assertContains($appointment->personnel_id, [$this->facialStaff->id, $this->massageStaff->id]);
        $this->assertSame(
            $this->facialStaff->id,
            $appointment->personnelAssignments()->where('position_id', $this->facialPositionId)->value('user_id')
        );
        $this->assertSame(
            $this->massageStaff->id,
            $appointment->personnelAssignments()->where('position_id', $this->massagePositionId)->value('user_id')
        );
    }
}
