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
            ['name' => 'Owner', 'email' => 'admin@nexflio.test', 'role_id' => User::ROLE_SUPER_ADMIN],
            ['name' => 'Manager', 'email' => 'manager@nexflio.test', 'role_id' => User::ROLE_MANAGER],
            ['name' => 'Client', 'email' => 'client@nexflio.test', 'role_id' => User::ROLE_CLIENT],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role_id' => $user['role_id'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
        }

        // Seed a staff employee with the extended profile structure
        $staffEmail = 'staff@nexflio.test';
        $staffUser = User::query()->updateOrCreate(
            ['email' => $staffEmail],
            [
                'name'              => 'Dela Cruz Sheila',
                'first_name'        => 'Sheila',
                'last_name'         => 'Dela Cruz',
                'middle_name'       => 'Marie',
                'contact_number'    => '09171234567',
                'username'          => 'delacruz.sheila',
                'role_id'           => User::ROLE_STAFF,
                'password'          => Hash::make('delacruz00000'),
                'email_verified_at' => now(),
            ]
        );

        StaffProfile::query()->updateOrCreate(
            ['user_id' => $staffUser->id],
            [
                'base_pay'          => 12000.00,
                'commission_rate'   => 10.00,
                'position'          => 'Massage Technician',
                'employment_status' => 'active',
                'hired_at'          => now()->toDateString(),
            ]
        );
    }
}
