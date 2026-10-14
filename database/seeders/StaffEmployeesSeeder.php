<?php

namespace Database\Seeders;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffEmployeesSeeder extends Seeder
{
    private const STAFF_LIST = [
        ['first' => 'Angelica', 'last' => 'Reyes', 'position' => 'Massage Technician', 'pay' => 13500.00],
        ['first' => 'Bianca', 'last' => 'Santos', 'position' => 'Massage Technician', 'pay' => 13000.00],
        ['first' => 'Catherine', 'last' => 'Lopez', 'position' => 'Nail Technician', 'pay' => 12500.00],
        ['first' => 'Danica', 'last' => 'Garcia', 'position' => 'Nail Technician', 'pay' => 12200.00],
        ['first' => 'Ericka', 'last' => 'Rivera', 'position' => 'Nail Technician', 'pay' => 12000.00],
        ['first' => 'Fiona', 'last' => 'Cruz', 'position' => 'Facial Technician', 'pay' => 14000.00],
        ['first' => 'Gwen', 'last' => 'Mendoza', 'position' => 'Facial Technician', 'pay' => 13800.00],
        ['first' => 'Hazel', 'last' => 'Torres', 'position' => 'Facial Technician', 'pay' => 13600.00],
        ['first' => 'Irene', 'last' => 'Flores', 'position' => 'Massage Technician', 'pay' => 13200.00],
        ['first' => 'Jasmine', 'last' => 'Castillo', 'position' => 'Nail Technician', 'pay' => 12400.00],
    ];

    public function run(): void
    {
        foreach (self::STAFF_LIST as $index => $data) {
            $last = trim($data['last']);
            $first = trim($data['first']);
            $middle = null;

            $fullName = trim($first.' '.$last);
            // Base identity is derived BEFORE any suffix and owns the email:
            // re-running the seeder must find the same row by that email.
            $baseUsername = strtolower(str_replace(' ', '.', $last.'.'.$first));
            $email = $baseUsername.'@nexflio.test';

            // A numeric suffix is appended ONLY when a DIFFERENT user
            // (different email) already holds the base username. The staff
            // member's own account from a previous run matches by email and
            // therefore never triggers a suffix — the row below updates it
            // in place instead of spawning a duplicate.
            $username = $baseUsername;
            if (User::withTrashed()->where('username', $baseUsername)->where('email', '!=', $email)->exists()) {
                $username .= ($index + 1);
            }

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name'              => $fullName,
                    'first_name'        => $first,
                    'last_name'         => $last,
                    'middle_name'       => $middle,
                    'username'          => $username,
                    'role_id'           => User::ROLE_STAFF,
                    'password'          => Hash::make(strtolower(preg_replace('/[^a-zA-Z]/', '', $last)) . '00000'),
                    'email_verified_at' => now(),
                ]
            );

            StaffProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'base_pay'          => $data['pay'],
                    'commission_rate'   => 10.00,
                    'position'          => $data['position'],
                    'employment_status' => 'active',
                    'hired_at'          => now()->toDateString(),
                ]
            );
        }
    }
}