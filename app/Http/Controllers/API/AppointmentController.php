<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    const PERSONNEL_ROLE_ID = 3;

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'personnel_id' => 'required|exists:users,id',
            'appointment_date' => 'required|date',
            'start_time' => 'required',
            'notes' => 'nullable|string',
            'payment_proof' => 'required|image|max:5120',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['payment_proof_path'] = $request->file('payment_proof')->store('payment_proofs', 'public');
        unset($validated['payment_proof']);

        $appointment = Appointment::create($validated);

        return $appointment->load(['user', 'service', 'personnel']);
    }

    public function personnel()
    {
        return User::where('role_id', self::PERSONNEL_ROLE_ID)
            ->select('id', 'name', 'email', 'role_id')
            ->get();
    }

    public function index(Request $request)
    {
        $query = Appointment::with(['user', 'service', 'personnel']);

        // Clients (role_id 4) only see their own appointments; staff/admin see all.
        if ($request->user()->role_id == 4) {
            $query->where('user_id', $request->user()->id);
        }

        return $query->get();
    }

    public function updateStatus(Request $request, $id)
    {
        $appointment = Appointment::with('service')->findOrFail($id);

        $request->validate(['status' => 'required']);

        $appointment->status = $request->status;
        $appointment->save();

        $serviceName = $appointment->service->name ?? 'your service';
        Notification::create([
            'user_id' => $appointment->user_id,
            'title' => 'Appointment ' . ucfirst($request->status),
            'body' => "Your booking for {$serviceName} is now {$request->status}.",
        ]);

        return response()->json(['message' => 'Appointment status updated to ' . $request->status]);
    }
}