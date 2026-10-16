<?php

namespace Tests\Feature;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Employee lifecycle: the Terminated status (settable from the edit
 * modal, shown as a badge, excluded from booking) blocks sign-in for any
 * non-active staff account, exactly like Inactive does.
 */
class EmployeeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const GATE_MESSAGE = 'This employee account is no longer active. Please contact the owner or manager.';

    private User $owner;

    private User $client;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::where('email', 'admin@nexflio.test')->firstOrFail();
        $this->client = User::where('email', 'client@nexflio.test')->firstOrFail();
        $this->staff = User::where('email', 'staff@nexflio.test')->firstOrFail();
    }

    private function setStatus(User $staff, string $status): void
    {
        $staff->staffProfile->update(['employment_status' => $status]);
    }

    public function test_admin_can_set_terminated_status_and_it_persists(): void
    {
        $this->actingAs($this->owner)->patch(
            route('admin.employees.update', $this->staff->id),
            [
                'base_pay' => 12000,
                'commission_rate' => 10,
                'position' => 'Massage Technician',
                'employment_status' => 'terminated',
            ]
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('terminated', $this->staff->fresh()->staffProfile->employment_status);

        // The list badge renders the status via ucfirst — no view change.
        $this->actingAs($this->owner)->get(route('admin.employees'))
            ->assertOk()
            ->assertSee('Terminated');
    }

    public function test_inactive_staff_cannot_log_in(): void
    {
        $this->setStatus($this->staff, 'inactive');

        $this->post('/login', ['email' => 'delacruz.sheila', 'password' => 'delacruz00000'])
            ->assertRedirect()
            ->assertSessionHasErrors(['email' => self::GATE_MESSAGE]);

        $this->assertGuest();
    }

    public function test_terminated_staff_cannot_log_in(): void
    {
        $this->setStatus($this->staff, 'terminated');

        $this->post('/login', ['email' => 'staff@nexflio.test', 'password' => 'delacruz00000'])
            ->assertRedirect()
            ->assertSessionHasErrors(['email' => self::GATE_MESSAGE]);

        $this->assertGuest();
    }

    public function test_terminated_staff_excluded_from_booking_pool(): void
    {
        $pool = $this->actingAs($this->client, 'sanctum')->getJson('/api/personnel');
        $this->assertContains($this->staff->id, array_column($pool->json(), 'id'));

        $this->setStatus($this->staff, 'terminated');

        $pool = $this->actingAs($this->client, 'sanctum')->getJson('/api/personnel');
        $this->assertNotContains($this->staff->id, array_column($pool->json(), 'id'));
    }

    public function test_staff_without_profile_signs_in_as_before(): void
    {
        $profileless = User::create([
            'name' => 'No Profile', 'email' => 'noprofile@nexflio.test',
            'password' => bcrypt('password'), 'role_id' => User::ROLE_STAFF,
            'email_verified_at' => now(),
        ]);
        $this->assertNull($profileless->staffProfile);

        $this->post('/login', ['email' => 'noprofile@nexflio.test', 'password' => 'password'])
            ->assertRedirect(route('staff.dashboard'));

        $this->assertAuthenticatedAs($profileless);
    }
}
