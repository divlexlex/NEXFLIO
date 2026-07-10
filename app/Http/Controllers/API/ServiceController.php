<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    // Para makuha lahat ng active services (para ipakita sa mobile app)
    public function index()
    {
        return Service::where('status', 'active')->get();
    }

    // Para mag-add ng new service (para sa Admin dashboard)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'category' => 'required|string',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'duration_minutes' => 'required|integer',
        ]);

        return Service::create($validated);
    }
}