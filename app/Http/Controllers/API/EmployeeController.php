<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function store(StoreEmployeeRequest $request)
    {
        $validated = $request->validated();

        $displayName = trim("{$validated['first_name']} ".($validated['middle_name'] ?? '')." {$validated['last_name']}");

        $user = DB::transaction(function () use ($validated, $displayName) {
            $username = self::generateUsername($validated['last_name'], $validated['first_name']);
            $tempPass = self::generateTempPassword($validated['last_name']);
            $email    = $username . '@nexflio.test';

            $user = User::create([
                'name'              => $displayName,
                'first_name'        => $validated['first_name'],
                'last_name'         => $validated['last_name'],
                'middle_name'       => $validated['middle_name'] ?? null,
                'contact_number'    => $validated['contact_number'],
                'email'             => $email,
                'username'          => $username,
                'password'          => Hash::make($tempPass),
                'role_id'           => User::ROLE_STAFF,
                'email_verified_at' => now(),
            ]);

            StaffProfile::create([
                'user_id'           => $user->id,
                'base_pay'          => $validated['base_pay'],
                'commission_rate'   => $validated['commission_rate'] ?? 10.00,
                'position'          => $validated['position'],
                'employment_status' => 'active',
                'hired_at'          => $validated['hired_at'] ?? now()->toDateString(),
            ]);

            return $user;
        });

        return response()->json($user->load('staffProfile'), 201);
    }

    private static function generateUsername(string $lastName, string $firstName): string
    {
        $last  = strtolower(preg_replace('/[^a-zA-Z]/', '', $lastName));
        $first = strtolower(preg_replace('/[^a-zA-Z]/', '', $firstName));

        $base = "{$last}.{$first}";

        if ($last === '' && $first === '') {
            $base = 'staff';
        }

        $username = $base;
        $suffix   = 2;
        while (User::where('username', $username)->exists()) {
            $username = $base . $suffix;
            $suffix++;
        }

        return $username;
    }

    private static function generateTempPassword(string $lastName): string
    {
        return strtolower(preg_replace('/[^a-zA-Z]/', '', $lastName)) . '00000';
    }
}
