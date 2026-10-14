<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Client online booking window: same-day booking is closed. The earliest
 * bookable date is tomorrow (or the next open day after it), enforced in
 * the wizard (minDate/clampDate), the schedule-step check, and both store
 * requests. Walk-ins (counter) are intentionally unaffected.
 */
class ClientBookingWindowTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
    }

    // (1) Branch schedule with no date parameter defaults to tomorrow's date
    // (no business-hours rows are seeded, so the next-open-day fallback is
    // exactly tomorrow).
    public function test_branch_schedule_defaults_to_tomorrow(): void
    {
        $service = Service::create([
            'name' => 'Window Massage (test)', 'category' => 'Massage', 'description' => 'Test',
            'price' => 599, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);
        $tomorrow = now()->addDay()->toDateString();

        $this->actingAs($this->client)
            ->get(route('account.booking.branch.schedule', ['service' => $service->id]))
            ->assertOk()
            ->assertSee('value="'.$tomorrow.'"', false)
            ->assertSee('min="'.$tomorrow.'"', false);
    }

    // (2) Same-day online booking fails validation; nothing is created.
    public function test_branch_store_rejects_today(): void
    {
        Storage::fake('public');

        $service = Service::create([
            'name' => 'Window Nails (test)', 'category' => 'Nails', 'description' => 'Test',
            'price' => 179, 'duration_minutes' => 45, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        $this->actingAs($this->client)->post(route('account.booking.branch.store'), [
            'service_id' => $service->id,
            'personnel_id' => 'any',
            'appointment_date' => now()->toDateString(),
            'start_time' => '10:00',
            'method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertSessionHasErrors('appointment_date');

        $this->assertSame(0, Appointment::count());
    }

    // (3) Tomorrow succeeds (same setup pattern as the fee suite).
    public function test_branch_store_accepts_tomorrow(): void
    {
        Storage::fake('public');

        $service = Service::create([
            'name' => 'Window Nails (test)', 'category' => 'Nails', 'description' => 'Test',
            'price' => 179, 'duration_minutes' => 45, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        $this->actingAs($this->client)->post(route('account.booking.branch.store'), [
            'service_id' => $service->id,
            'personnel_id' => 'any',
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'method' => 'gcash',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertRedirect();

        $this->assertSame(1, Appointment::count());
    }

    // Fix 1 render check: no hardcoded is-selected on the variant cards;
    // the first tier radio is still preselected.
    public function test_package_detail_has_no_hardcoded_selected_card(): void
    {
        $package = Service::where('name', 'Package DEE')->firstOrFail();
        $this->assertTrue($package->variants()->exists());

        $this->actingAs($this->client)
            ->get(route('account.booking.branch.service.show', $package->id))
            ->assertOk()
            ->assertDontSee('nx-select-card d-flex flex-column gap-1 is-selected', false)
            ->assertSee('name="variant"', false)
            ->assertSee('checked', false);
    }
}
