<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Inventory;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {

        $upcoming = Appointment::where('appointment_date', '>=', now()->toDateString())
            ->where('status', 'pending')
            ->count();

        $lowStocks = Inventory::whereColumn('quantity', '<=', 'reorder_point')->get();

        $todayBookings = Appointment::whereDate('appointment_date', now()->toDateString())->count();

        return response()->json([
            'upcoming_appointments_count' => $upcoming,
            'today_bookings_count' => $todayBookings,
            'low_stock_alerts' => $lowStocks,
            'message' => 'Dashboard data retrieved successfully'
        ]);
    }
}