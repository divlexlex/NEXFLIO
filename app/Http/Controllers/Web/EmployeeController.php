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
                ->orderBy('name')
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

        DB::transaction(function () use ($validated) {
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
        });

        return back()->with('success', 'Employee added.');
    }

    public function update(Request $request, $id)
    {
        $user = User::where('role_id', User::ROLE_STAFF)->with('staffProfile')->findOrFail($id);

        $validated = $request->validate([
            'base_pay' => 'required|numeric|min:0',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'position' => 'nullable|string|max:255',
            'employment_status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        // No hard deletes: leaving is a status flag, never a removed row.
        $user->staffProfile
            ? $user->staffProfile->update($validated)
            : StaffProfile::create(['user_id' => $user->id] + $validated);

        return back()->with('success', 'Employee updated.');
    }
}
