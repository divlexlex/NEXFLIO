<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::where('email', 'admin@nexflio.test')->firstOrFail();
    }

    private function employeePayload(array $overrides = []): array
    {
        return array_merge([
            'first_name'       => 'Juan',
            'last_name'        => 'Dela Cruz',
            'contact_number'   => '09171234567',
            'position'         => 'Nail Technician',
            'base_pay'         => 12000,
            'commission_rate'  => 10,
            'hired_at'         => now()->toDateString(),
        ], $overrides);
    }

    public function test_creating_an_employee_auto_generates_username_and_password(): void
    {
        $response = $this->actingAs($this->owner)->post('/admin/employees', $this->employeePayload());

        $response->assertRedirect()->assertSessionHasNoErrors();

        $user = User::where('username', 'delacruz.juan')->firstOrFail();
        $this->assertSame('delacruz.juan', $user->username);
        $this->assertSame(User::ROLE_STAFF, (int) $user->role_id);
        $this->assertNotNull($user->email_verified_at);

        $creds = session('newStaffCredentials');
        $this->assertSame('delacruz.juan', $creds['username']);
        $this->assertSame('delacruz00000', $creds['password']);
    }

    public function test_duplicate_name_gets_a_unique_suffixed_username(): void
    {
        $this->actingAs($this->owner)->post('/admin/employees', $this->employeePayload());

        $this->actingAs($this->owner)->post('/admin/employees', $this->employeePayload());

        $this->assertDatabaseHas('users', ['username' => 'delacruz.juan']);
        $this->assertDatabaseHas('users', ['username' => 'delacruz.juan2']);
    }

    public function test_new_staff_can_log_in_immediately_with_the_generated_credentials(): void
    {
        $this->actingAs($this->owner)->post('/admin/employees', $this->employeePayload([
            'first_name' => 'Maria',
            'last_name'  => 'Santos',
        ]));

        $creds = session('newStaffCredentials');

        $response = $this->post('/login', [
            'email' => $creds['username'],
            'password' => $creds['password'],
        ]);

        $response->assertRedirect(route('staff.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_staff_can_change_their_own_password(): void
    {
        $this->actingAs($this->owner)->post('/admin/employees', $this->employeePayload([
            'first_name' => 'Pedro',
            'last_name'  => 'Reyes',
        ]));
        $creds = session('newStaffCredentials');
        $staff = User::where('username', $creds['username'])->firstOrFail();

        $response = $this->actingAs($staff)->patch('/staff/password', [
            'current_password' => $creds['password'],
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        // Old password no longer works, new one does.
        $this->post('/logout');
        $this->post('/login', ['email' => $creds['username'], 'password' => $creds['password']])
            ->assertSessionHasErrors('password');
        $this->post('/login', ['email' => $creds['username'], 'password' => 'newpassword123'])
            ->assertRedirect(route('staff.dashboard'));
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $staff = User::where('email', 'staff@nexflio.test')->firstOrFail();

        $response = $this->actingAs($staff)->patch('/staff/password', [
            'current_password' => 'wrong-password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('current_password');
    }
}
