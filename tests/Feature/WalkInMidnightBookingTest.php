<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BusinessHours;
use App\Models\Service;
use App\Models\User;
use App\Support\WalkInHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Walk-in Entry may finish until config('booking.walk_in.close_at')
 * (default 01:00 next morning). Online bookings still refuse anything that
 * would run past BusinessHours close (default 10:00 PM).
 */
class WalkInMidnightBookingTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    private User $manager;

    private User $staff;

    private Service $twoHour;

    private Service $oneHour;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();

        $this->twoHour = Service::create([
            'name' => 'Midnight Swedish 120',
            'category' => 'Massage',
            'price' => 999,
            'duration_minutes' => 120,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);
        $this->oneHour = Service::create([
            'name' => 'Midnight Swedish 60',
            'category' => 'Massage',
            'price' => 599,
            'duration_minutes' => 60,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);

        // Deterministic day: open noon, shop close 10 PM (online rule).
        $this->date = now()->toDateString();
        BusinessHours::where('day_of_week', Carbon::parse($this->date)->dayOfWeek)
            ->update(['open_at' => '12:00:00', 'close_at' => '22:00:00', 'is_open' => true]);

        config(['booking.walk_in.close_at' => '01:00']);
    }

    public function test_walk_in_hours_helper_matches_midnight_examples(): void
    {
        $this->assertSame('01:00', WalkInHours::closeAt());

        // 2-hour massage, 10:00 PM → 12:00 AM ✅
        $this->assertTrue(WalkInHours::startFitsOnDate($this->date, '22:00', 120));
        // 2-hour massage, 11:00 PM → 1:00 AM ✅
        $this->assertTrue(WalkInHours::startFitsOnDate($this->date, '23:00', 120));
        // 2-hour massage, 11:01 PM → 1:01 AM ❌
        $this->assertFalse(WalkInHours::startFitsOnDate($this->date, '23:01', 120));
        // 1-hour massage, 12:00 AM → 1:00 AM ✅
        $this->assertTrue(WalkInHours::startFitsOnDate($this->date, '00:00', 60));
        // 1-hour massage, 12:01 AM → 1:01 AM ❌
        $this->assertFalse(WalkInHours::startFitsOnDate($this->date, '00:01', 60));
    }

    public function test_online_still_rejects_starts_that_finish_after_shop_close(): void
    {
        Storage::fake('public');

        // Same 11:00 PM start that walk-in allows — online must still refuse
        // (would finish 1:00 AM, past the 10:00 PM BusinessHours close).
        $onlineDate = now()->addDay()->toDateString();
        BusinessHours::where('day_of_week', Carbon::parse($onlineDate)->dayOfWeek)
            ->update(['open_at' => '12:00:00', 'close_at' => '22:00:00', 'is_open' => true]);
        $this->actingAs($this->client)->post(route('account.booking.branch.store'), [
            'service_id' => $this->twoHour->id,
            'personnel_id' => (string) $this->staff->id,
            'appointment_date' => $onlineDate,
            'start_time' => '23:00',
            'method' => 'gcash',
            'guests' => 1,
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertSessionHasErrors('start_time');

        $this->assertSame(0, Appointment::count());

        $this->assertFalse(BusinessHours::startFitsOnDate($this->date, '22:00', 120));
        $this->assertFalse(BusinessHours::startFitsOnDate($this->date, '23:00', 120));
        $this->assertTrue(BusinessHours::startFitsOnDate($this->date, '21:00', 60));
    }

    public function test_walk_in_accepts_10pm_and_11pm_two_hour_massages(): void
    {
        $this->actingAs($this->manager)->post(route('admin.appointments.walk-in'), [
            'walk_in_name' => 'Ten PM Guest',
            'service_id' => $this->twoHour->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => $this->date,
            'start_time' => '22:00',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->actingAs($this->manager)->post(route('admin.appointments.walk-in'), [
            'walk_in_name' => 'Eleven PM Guest',
            'service_id' => $this->twoHour->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => $this->date,
            'start_time' => '23:00',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'walk_in_name' => 'Ten PM Guest',
            'start_time' => '22:00',
        ]);
        $this->assertDatabaseHas('appointments', [
            'walk_in_name' => 'Eleven PM Guest',
            'start_time' => '23:00',
        ]);
    }

    public function test_walk_in_rejects_1101pm_two_hour_massage(): void
    {
        $this->actingAs($this->manager)->post(route('admin.appointments.walk-in'), [
            'walk_in_name' => 'Too Late Guest',
            'service_id' => $this->twoHour->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => $this->date,
            'start_time' => '23:01',
        ])->assertSessionHasErrors('start_time');

        $this->assertDatabaseMissing('appointments', ['walk_in_name' => 'Too Late Guest']);
    }

    public function test_walk_in_accepts_midnight_one_hour_massage(): void
    {
        $this->actingAs($this->manager)->post(route('admin.appointments.walk-in'), [
            'walk_in_name' => 'Midnight Guest',
            'service_id' => $this->oneHour->id,
            'personnel_id' => $this->staff->id,
            'appointment_date' => $this->date,
            'start_time' => '00:00',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'walk_in_name' => 'Midnight Guest',
            'appointment_date' => $this->date.' 00:00:00',
            'start_time' => '00:00',
        ]);
    }

    public function test_walk_in_close_at_is_configurable(): void
    {
        config(['booking.walk_in.close_at' => '00:30']);

        // 11:00 PM + 120 = 1:00 AM — past the tightened 00:30 close.
        $this->assertFalse(WalkInHours::startFitsOnDate($this->date, '23:00', 120));
        // 10:30 PM + 120 = 00:30 — exact close, allowed.
        $this->assertTrue(WalkInHours::startFitsOnDate($this->date, '22:30', 120));
    }

    public function test_index_exposes_walk_in_close_for_the_modal(): void
    {
        $this->actingAs($this->manager)
            ->get(route('admin.appointments'))
            ->assertOk()
            ->assertSee('nxWalkInCloseAt', false)
            ->assertSee('01:00', false);
    }
}
