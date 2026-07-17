<?php

namespace Database\Seeders;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Owner', 'email' => 'owner@nexflio.test', 'role_id' => User::ROLE_SUPER_ADMIN],
            ['name' => 'Manager', 'email' => 'manager@nexflio.test', 'role_id' => User::ROLE_MANAGER],
            ['name' => 'Staff Member', 'email' => 'staff@nexflio.test', 'role_id' => User::ROLE_STAFF],
            ['name' => 'Client', 'email' => 'client@nexflio.test', 'role_id' => User::ROLE_CLIENT],
        ];

        foreach ($users as $user) {
            $created = User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role_id' => $user['role_id'],
                    'password' => Hash::make('password'),
                ]
            );

            // Staff need a profile to appear in the booking pool.
            if ($user['role_id'] === User::ROLE_STAFF) {
                StaffProfile::query()->updateOrCreate(
                    ['user_id' => $created->id],
                    [
                        'base_pay' => 12000.00,
                        'commission_rate' => 10.00,
                        'position' => 'Nail Technician',
                        'employment_status' => 'active',
                        'hired_at' => now()->toDateString(),
                    ]
                );
            }
        }
    }
}
