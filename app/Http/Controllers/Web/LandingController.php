<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\Service;

class LandingController extends Controller
{
    public function index()
    {
        $servicesByCategory = Service::where('status', 'active')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        $promos = Promo::where('is_active', true)->with('service')->get();

        return view('landing.index', [
            'servicesByCategory' => $servicesByCategory,
            'promos' => $promos,
        ]);
    }
}
