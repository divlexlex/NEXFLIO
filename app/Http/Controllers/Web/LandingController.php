<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\Service;

class LandingController extends Controller
{
    public function index()
    {
        // The full Services catalog now lives only on the dedicated /services
        // page (see ServicesController + App\Support\ServiceCatalog) — Home no
        // longer needs it.
        $promos = Promo::live()->with('services')->get();

        // Dynamic recommendations: a fresh random mix of services and promos
        // on every page load, each linking to its detail page.
        $recommendations = collect()
            ->concat(Service::where('status', 'active')->get()->map(fn ($s) => [
                'type' => 'service', 'id' => $s->id, 'title' => $s->name,
                'subtitle' => $s->category, 'price' => $s->price, 'image_url' => $s->image_url,
            ]))
            ->concat($promos->filter(fn ($p) => $p->displayPrice() !== null)->map(fn ($p) => [
                'type' => 'promo', 'id' => $p->id, 'title' => $p->title,
                'subtitle' => $p->services->first()?->name, 'price' => $p->displayPrice(), 'image_url' => $p->image_url,
            ]))
            ->shuffle()
            ->take(8)
            ->values();

        return view('landing.index', [
            'promos' => $promos,
            'recommendations' => $recommendations,
            // Temporary frontend-only hero imagery — see config/hero_slider.php.
            'heroSlides' => config('hero_slider.slides', []),
        ]);
    }
}
