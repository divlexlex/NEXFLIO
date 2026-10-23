<?php

namespace Tests\Feature;

use App\Models\BusinessHours;
use App\Models\Position;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data-driven staff positions: seeded classics, manager store endpoint,
 * employee forms, roster pills, and booking eligibility from the table.
 */
class PositionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $manager;

    private User $owner;

    private User $client;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::where('email', 'admin@nexflio.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();

        foreach (range(0, 6) as $day) {
            BusinessHours::updateOrCreate(
                ['day_of_week' => $day],
                ['is_open' => true, 'open_at' => '10:00', 'close_at' => '21:00']
            );
        }
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

    private function employeePayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Lash',
            'last_name' => 'Artist',
            'contact_number' => '09171234567',
            'position' => 'Nail Technician',
            'base_pay' => 12000,
            'commission_rate' => 10,
            'hired_at' => now()->toDateString(),
        ], $overrides);
    }

    public function test_classic_positions_seeded_with_category_links(): void
    {
        $this->assertSame(
            ['Nail Technician', 'Massage Technician', 'Facial Technician'],
            Position::orderBy('id')->pluck('name')->all()
        );
        $this->assertSame(
            ['Nail Technician'],
            Position::namesForServiceCategory('Nails')
        );
        $this->assertSame(
            ['Massage Technician'],
            Position::namesForServiceCategory('Massage')
        );
        $this->assertSame(
            ['Facial Technician'],
            Position::namesForServiceCategory('Facial')
        );
        $this->assertSame([], Position::namesForServiceCategory('Aesthetics'));
    }

    public function test_manager_can_store_a_position_with_and_without_a_category(): void
    {
        $linked = $this->actingAs($this->manager)->postJson(
            route('admin.positions.store'),
            ['name' => 'Lash Artist', 'service_category' => 'Lashes & Brows']
        )->assertCreated()->json();

        $this->assertSame('Lash Artist', $linked['name']);
        $this->assertSame('Lashes & Brows', $linked['service_category']);
        $this->assertDatabaseHas('positions', ['name' => 'Lash Artist', 'service_category' => 'Lashes & Brows']);

        $this->actingAs($this->manager)->postJson(
            route('admin.positions.store'),
            ['name' => 'Receptionist']
        )->assertCreated();

        $this->assertDatabaseHas('positions', ['name' => 'Receptionist', 'service_category' => null]);
    }

    public function test_store_position_rejects_duplicates_and_bad_input(): void
    {
        $this->actingAs($this->manager)->postJson(
            route('admin.positions.store'),
            ['name' => 'Nail Technician']
        )->assertStatus(422);

        // Case-insensitive duplicate.
        $this->actingAs($this->manager)->postJson(
            route('admin.positions.store'),
            ['name' => 'nail technician']
        )->assertStatus(422);

        $this->assertSame(3, Position::count());

        $this->actingAs($this->manager)->postJson(
            route('admin.positions.store'),
            ['name' => 'Bogus', 'service_category' => 'Not a category']
        )->assertStatus(422);

        $this->actingAs($this->manager)->postJson(
            route('admin.positions.store'),
            ['name' => '']
        )->assertStatus(422);
    }

    public function test_store_position_forbidden_for_client_and_staff(): void
    {
        $this->actingAs($this->client)->postJson(
            route('admin.positions.store'),
            ['name' => 'Sneaky']
        )->assertForbidden();

        $this->actingAs($this->staff)->postJson(
            route('admin.positions.store'),
            ['name' => 'Sneaky']
        )->assertForbidden();

        $this->assertDatabaseMissing('positions', ['name' => 'Sneaky']);
    }

    public function test_store_position_plain_post_fallback_redirects_with_flash(): void
    {
        $this->actingAs($this->manager)->post(
            route('admin.positions.store'),
            ['name' => 'Receptionist']
        )->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('positions', ['name' => 'Receptionist']);
    }

    public function test_employee_forms_list_positions_with_new_entry_last(): void
    {
        $html = $this->actingAs($this->manager)->get(route('admin.employees'))->assertOk()->getContent();

        foreach (['Nail Technician', 'Massage Technician', 'Facial Technician'] as $name) {
            $this->assertStringContainsString('<option value="'.$name.'">'.$name.'</option>', $html);
        }
        $this->assertStringContainsString('<option value="__new__">+ New position…</option>', $html);
        $this->assertStringContainsString('id="newPositionModal"', $html);
        $this->assertStringContainsString('Handles service category', $html);
    }

    public function test_employee_creation_with_custom_position_succeeds(): void
    {
        $this->actingAs($this->manager)->postJson(
            route('admin.positions.store'),
            ['name' => 'Lash Artist', 'service_category' => 'Lashes & Brows']
        )->assertCreated();

        $this->actingAs($this->owner)->post(
            route('admin.employees.store'),
            $this->employeePayload(['position' => 'Lash Artist'])
        )->assertRedirect()->assertSessionHasNoErrors();

        $user = User::where('username', 'artist.lash')->firstOrFail();
        $this->assertSame('Lash Artist', $user->staffProfile->position);

        $this->actingAs($this->manager)->get(route('admin.employees'))
            ->assertOk()
            ->assertSee('Lash Artist');
    }

    public function test_roster_lists_custom_position_without_code_changes(): void
    {
        $this->actingAs($this->manager)->postJson(
            route('admin.positions.store'),
            ['name' => 'Lash Artist', 'service_category' => 'Lashes & Brows']
        )->assertCreated();

        $html = $this->actingAs($this->manager)->get(route('admin.availability'))->assertOk()->getContent();

        $this->assertStringContainsString('data-cat="Lash Artist"', $html);
    }

    public function test_booking_eligibility_uses_position_links(): void
    {
        $service = Service::create([
            'name' => 'Position Test Manicure', 'category' => 'Nails', 'description' => 'Test',
            'price' => 500, 'duration_minutes' => 60, 'status' => 'active', 'service_location_type' => 'branch',
        ]);

        $this->actingAs($this->manager)->postJson(
            route('admin.positions.store'),
            ['name' => 'Junior Nail Tech', 'service_category' => 'Nails']
        )->assertCreated();
        $this->actingAs($this->manager)->postJson(
            route('admin.positions.store'),
            ['name' => 'Receptionist']
        )->assertCreated();

        $linked = $this->makeStaff('Nina', 'Linked', 'Junior Nail Tech');
        $unlinked = $this->makeStaff('Uma', 'Unlinked', 'Receptionist');

        $html = $this->actingAs($this->client)->get(route('account.booking.branch.schedule', [
            'service' => $service->id, 'date' => now()->addDay()->toDateString(), 'personnel' => 'any',
        ]))->assertOk()->getContent();

        // Classic + custom linked positions stay in the Nails pool …
        $this->assertStringContainsString('Nina Linked', $html);
        // … the unlinked custom position joins no pool and breaks nothing.
        $this->assertStringNotContainsString('data-personnel-name="Uma Unlinked"', $html);
    }

    public function test_package_booking_eligibility_uses_selected_employee_categories(): void
    {
        $facialId = Position::where('name', 'Facial Technician')->value('id');
        $massageId = Position::where('name', 'Massage Technician')->value('id');

        $package = Service::create([
            'name' => 'Position Test Package',
            'category' => 'Packages',
            'description' => 'Test',
            'package_details' => "Facial\nMassage",
            'price' => 1500,
            'duration_minutes' => 90,
            'status' => 'active',
            'service_location_type' => 'branch',
        ]);
        $package->positions()->sync([$facialId, $massageId]);

        $facialStaff = $this->makeStaff('Faye', 'Facial', 'Facial Technician');
        $massageStaff = $this->makeStaff('Maya', 'Massage', 'Massage Technician');
        $nailStaff = $this->makeStaff('Nora', 'Nails', 'Nail Technician');

        $html = $this->actingAs($this->client)->get(route('account.booking.branch.schedule', [
            'service' => $package->id, 'date' => now()->addDay()->toDateString(), 'personnel' => 'any',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('Faye Facial', $html);
        $this->assertStringContainsString('Maya Massage', $html);
        $this->assertStringNotContainsString('data-personnel-name="Nora Nails"', $html);
    }
}
