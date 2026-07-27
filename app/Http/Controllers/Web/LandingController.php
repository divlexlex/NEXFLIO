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

        // Dynamic recommendations: a fresh random mix of services and promos
        // on every page load, each linking to its detail page.
        $recommendations = collect()
            ->concat(Service::where('status', 'active')->get()->map(fn ($s) => [
                'type' => 'service', 'id' => $s->id, 'title' => $s->name,
                'subtitle' => $s->category, 'price' => $s->price, 'image_url' => $s->image_url,
            ]))
            ->concat($promos->map(fn ($p) => [
                'type' => 'promo', 'id' => $p->id, 'title' => $p->title,
                'subtitle' => $p->service?->name, 'price' => $p->price, 'image_url' => $p->image_url,
            ]))
            ->shuffle()
            ->take(8)
            ->values();

        return view('landing.index', [
            'servicesByCategory' => $servicesByCategory,
            'promos' => $promos,
            'recommendations' => $recommendations,
        ]);
    }
}
