<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterRoleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_registration_ignores_client_supplied_role(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Eve Attacker',
            'email' => 'eve@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => User::ROLE_SUPER_ADMIN,
        ]);

        $response->assertCreated();

        $user = User::where('email', 'eve@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_CLIENT, (int) $user->role_id);
    }

    public function test_registration_defaults_to_client_role(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Normal Client',
            'email' => 'client2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();

        $user = User::where('email', 'client2@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_CLIENT, (int) $user->role_id);
    }
}
