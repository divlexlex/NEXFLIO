<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Models\Attendance;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', now()->toDateString());

        return view('admin.employees.index', [
            'employees' => User::where('role_id', User::ROLE_STAFF)
                ->with('staffProfile')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'attendances' => Attendance::with('user:id,name')
                ->whereDate('work_date', $date)
                ->orderBy('time_in')
                ->get()
                ->keyBy('user_id'),
            'attendanceDate' => $date,
        ]);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $validated = $request->validated();

        $displayName = trim("{$validated['first_name']} ".($validated['middle_name'] ?? '')." {$validated['last_name']}");
        $username    = $this->generateUsername($validated['last_name'], $validated['first_name']);
        $tempPass    = $this->generateTempPassword($validated['last_name']);
        $email       = $username . '@nexflio.test';

        DB::transaction(function () use ($validated, $displayName, $username, $tempPass, $email) {
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
                'commission_rate'   => $validated['commission_rate'],
                'position'          => $validated['position'],
                'employment_status' => 'active',
                'hired_at'          => $validated['hired_at'],
            ]);
        });

        return back()->with([
            'success' => 'Employee added.',
            'newStaffCredentials' => [
                'name'     => $displayName,
                'username' => $username,
                'password' => $tempPass,
                'position' => $validated['position'],
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::where('role_id', User::ROLE_STAFF)->with('staffProfile')->findOrFail($id);

        $validated = $request->validate([
            'base_pay'          => 'required|numeric|min:0',
            'commission_rate'   => 'required|numeric|min:0|max:100',
            'position'          => ['required', Rule::in(array_column(\App\Enums\Position::cases(), 'value'))],
            'employment_status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $user->staffProfile
            ? $user->staffProfile->update($validated)
            : StaffProfile::create(['user_id' => $user->id] + $validated);

        return back()->with('success', 'Employee updated.');
    }

    public function destroy($id)
    {
        $user = User::where('role_id', User::ROLE_STAFF)->findOrFail($id);
        $user->delete();

        return back()->with('success', 'Employee removed.');
    }

    /**
     * Generate username: lastname.firstname
     * e.g. "Dela Cruz" + "Sheila" → delacruz.sheila
     * Duplicates: delacruz.sheila2, delacruz.sheila3, etc.
     */
    private function generateUsername(string $lastName, string $firstName): string
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

    /**
     * Generate a temporary password: lowercase(lastname) + "00000"
     * e.g. "Dela Cruz" → "delacruz00000"
     */
    private function generateTempPassword(string $lastName): string
    {
        return strtolower(preg_replace('/[^a-zA-Z]/', '', $lastName)) . '00000';
    }
}
