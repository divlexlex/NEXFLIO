<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'service_id' => 'required|exists:services,id',
            'personnel_id' => 'required|exists:users,id',
            'appointment_date' => 'required|date',
            'start_time' => 'required',
        ]);

        return Appointment::create($validated);
    }

    public function index()
    {
        return Appointment::with(['user', 'service', 'personnel'])->get();
    }

    public function updateStatus(Request $request, $id)
    {
        $appointment = Appointment::findOrFail($id);
        
        $request->validate(['status' => 'required']);
        
        $appointment->status = $request->status;
        $appointment->save();

        return response()->json(['message' => 'Appointment status updated to ' . $request->status]);
    }
}