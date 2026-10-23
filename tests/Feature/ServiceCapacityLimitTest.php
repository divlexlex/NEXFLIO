<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Position;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\ServiceCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceCapacityLimitTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    private User $manager;

    private User $staffA;

    private User $staffB;

    private User $staffC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staffA = User::where('email', 'staff@nexflio.test')->firstOrFail();
        $this->staffB = $this->makeStaff('Capacity', 'Beta', 'Massage Technician');
        $this->staffC = $this->makeStaff('Capacity', 'Gamma', 'Massage Technician');
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

    private function ensureAestheticsPosition(): void
    {
        Position::updateOrCreate(
            ['name' => 'Aesthetics Technician'],
            ['service_category' => 'Aesthetics']
        );
    }

    private function makeService(string $name, string $category, int $duration = 60): Service
    {
        return Service::create([
            'name' => $name,
            'category' => $category,
            'price' => 500,
            'duration_minutes' => $duration,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);
    }

    private function book(
        Service $service,
        User $staff,
        string $date,
        string $time,
        AppointmentStatus $status = AppointmentStatus::Booked
    ): Appointment {
        $appointment = Appointment::create([
            'user_id' => $this->client->id,
            'service_id' => $service->id,
            'personnel_id' => $staff->id,
            'appointment_date' => $date,
            'start_time' => $time,
            'status' => $status,
            'booked_price' => $service->price,
        ]);
        Payment::create([
            'appointment_id' => $appointment->id,
            'amount' => $service->price,
            'method' => 'gcash',
            'status' => PaymentStatus::Verified,
            'verified_by' => $this->manager->id,
            'verified_at' => now(),
        ]);

        return $appointment;
    }

    public function test_massage_allows_two_concurrent_clients_but_blocks_a_third(): void
    {
        $massage = $this->makeService('Cap Swedish', 'Massage');
        $date = now()->addDay()->toDateString();

        $this->book($massage, $this->staffA, $date, '14:00');
        $this->book($massage, $this->staffB, $date, '14:00');

        $this->assertFalse(ServiceCapacity::hasCapacity($massage, $date, '14:00'));
        $this->assertTrue(ServiceCapacity::hasCapacity($massage, $date, '15:00'));
    }

    public function test_massage_capacity_counts_overlapping_durations(): void
    {
        $massage = $this->makeService('Cap Overlap Swedish', 'Massage', 60);
        $date = now()->addDay()->toDateString();

        $this->book($massage, $this->staffA, $date, '14:00'); // 14:00–15:00
        $this->book($massage, $this->staffB, $date, '14:00');

        // 14:30 overlaps both full beds → blocked
        $this->assertFalse(ServiceCapacity::hasCapacity($massage, $date, '14:30'));
    }

    public function test_head_spa_allows_four_concurrent_clients(): void
    {
        $headSpa = $this->makeService('Cap Japanese Head Spa', 'Head Spa', 30);
        $date = now()->addDay()->toDateString();
        $staff = [
            $this->staffA,
            $this->staffB,
            $this->staffC,
            $this->makeStaff('Capacity', 'Delta', 'Facial Technician'),
        ];

        foreach ($staff as $i => $person) {
            $this->book($headSpa, $person, $date, '11:00');
            $this->assertSame($i + 1, ServiceCapacity::usedSeats($date, 11 * 60, 30, 'head_spa'));
        }

        $this->assertFalse(ServiceCapacity::hasCapacity($headSpa, $date, '11:00'));
        $this->assertTrue(ServiceCapacity::hasCapacity($headSpa, $date, '12:00'));
    }

    public function test_facial_and_aesthetics_share_the_single_esthetics_slot(): void
    {
        $facial = $this->makeService('Cap Basic Facial', 'Facial', 45);
        $aesthetic = $this->makeService('Cap Diamond Peel', 'Aesthetics', 45);
        $date = now()->addDay()->toDateString();

        $this->book($facial, $this->staffA, $date, '13:00');

        // Facial + Aesthetics share the single esthetics room: a booked
        // Facial blocks both another Facial and an overlapping Aesthetics
        // booking.
        $this->assertFalse(ServiceCapacity::hasCapacity($facial, $date, '13:00'));
        $this->assertFalse(ServiceCapacity::hasCapacity($aesthetic, $date, '13:00'));

        // The room frees up when the facial ends (13:00 + 45m = 13:45)...
        $this->assertTrue(ServiceCapacity::hasCapacity($aesthetic, $date, '13:45'));

        // ...and the reverse holds too: an Aesthetics booking blocks an
        // overlapping Facial.
        $this->book($aesthetic, $this->staffB, $date, '14:00');
        $this->assertFalse(ServiceCapacity::hasCapacity($facial, $date, '14:00'));
    }


    public function test_cancelled_appointments_do_not_consume_capacity(): void
    {
        $massage = $this->makeService('Cap Cancel Swedish', 'Massage');
        $date = now()->addDay()->toDateString();

        $this->book($massage, $this->staffA, $date, '10:00', AppointmentStatus::Cancelled);

        $this->assertTrue(ServiceCapacity::hasCapacity($massage, $date, '10:00'));
    }

    public function test_home_service_is_not_limited_by_branch_beds(): void
    {
        $home = $this->makeService('Cap Home Swedish', 'Home Service', 90);
        $date = now()->addDay()->toDateString();

        $this->assertSame([], ServiceCapacity::poolsForService($home));
        $this->assertTrue(ServiceCapacity::hasCapacity($home, $date, '14:00'));
    }

    public function test_branch_store_rejects_when_massage_beds_full(): void
    {
        Storage::fake('public');
        $massage = $this->makeService('Cap Store Swedish', 'Massage');
        $date = now()->addDay()->toDateString();

        $this->book($massage, $this->staffA, $date, '16:00');
        $this->book($massage, $this->staffB, $date, '16:00');

        $this->actingAs($this->client)->post(route('account.booking.branch.store'), [
            'service_id' => $massage->id,
            'personnel_id' => (string) $this->staffC->id,
            'appointment_date' => $date,
            'start_time' => '16:00',
            'method' => 'gcash',
            'guests' => 1,
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertSessionHasErrors('start_time');

        $this->assertSame(2, Appointment::where('service_id', $massage->id)->count());
    }

    public function test_schedule_hides_full_esthetics_slot(): void
    {
        $this->ensureAestheticsPosition();

        $aesthetic = $this->makeService('Cap Slot Aesthetics', 'Aesthetics', 60);
        // Aesthetics technicians for eligibility.
        $tech = $this->makeStaff('Capacity', 'Aes', 'Aesthetics Technician');
        $date = now()->addDays(2)->toDateString();

        $this->book($aesthetic, $tech, $date, '15:00');

        $html = $this->actingAs($this->client)->get(route('account.booking.branch.schedule', [
            'service' => $aesthetic->id,
            'date' => $date,
            'personnel' => (string) $this->makeStaff('Capacity', 'AesTwo', 'Aesthetics Technician')->id,
        ]))->assertOk()->getContent();

        // 15:00 must appear as unavailable (no time=15%3A00 link, or marked unavailable).
        // Available slots are links with time=; unavailable are disabled spans without the query param.
        $this->assertDoesNotMatchRegularExpression(
            '/href="[^"]*time=15%3A00[^"]*"/',
            $html
        );
    }

    public function test_walk_in_rejects_when_esthetics_full(): void
    {
        $this->ensureAestheticsPosition();

        $aesthetic = $this->makeService('Cap Walk Aesthetics', 'Aesthetics');
        $tech = $this->makeStaff('Capacity', 'WalkAes', 'Aesthetics Technician');
        $date = now()->toDateString();

        $this->book($aesthetic, $tech, $date, '12:00');

        $other = $this->makeStaff('Capacity', 'WalkAes2', 'Aesthetics Technician');

        $this->actingAs($this->manager)->post(route('admin.appointments.walk-in'), [
            'walk_in_name' => 'Full Room Guest',
            'service_id' => $aesthetic->id,
            'personnel_id' => $other->id,
            'appointment_date' => $date,
            'start_time' => '12:00',
        ])->assertSessionHasErrors('start_time');
    }
}
