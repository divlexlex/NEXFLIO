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

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role_id' => User::ROLE_STAFF,
            ]);

            StaffProfile::create([
                'user_id' => $user->id,
                'base_pay' => $validated['base_pay'],
                'commission_rate' => $validated['commission_rate'] ?? 10.00,
                'position' => $validated['position'] ?? null,
                'employment_status' => 'active',
                'hired_at' => $validated['hired_at'] ?? now()->toDateString(),
            ]);

            return $user;
        });

        return response()->json($user->load('staffProfile'), 201);
    }
}
