<?php

namespace App\Http\Controllers\API;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Inventory;

class DashboardController extends Controller
{
    public function index()
    {
        $upcoming = Appointment::where('appointment_date', '>=', now()->toDateString())
            ->where('status', AppointmentStatus::Booked)
            ->count();

        $awaitingVerification = Appointment::where('status', AppointmentStatus::Unverified)->count();

        $lowStocks = Inventory::whereColumn('quantity', '<=', 'reorder_point')->get();

        $todayBookings = Appointment::whereDate('appointment_date', now()->toDateString())->count();

        return response()->json([
            'upcoming_appointments_count' => $upcoming,
            'awaiting_verification_count' => $awaitingVerification,
            'today_bookings_count' => $todayBookings,
            'low_stock_alerts' => $lowStocks,
            'message' => 'Dashboard data retrieved successfully',
        ]);
    }
}
