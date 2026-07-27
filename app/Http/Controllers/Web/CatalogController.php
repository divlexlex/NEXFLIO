<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\Service;

/**
 * Public detail pages for services and promos. Booking/buying still
 * happens in the mobile app, so each detail page's primary CTA opens the
 * "Get the app" modal.
 */
class CatalogController extends Controller
{
    public function service($id)
    {
        $service = Service::where('status', 'active')->findOrFail($id);

        return view('catalog.detail', [
            'type' => 'service',
            'title' => $service->name,
            'category' => $service->category,
            'price' => $service->price,
            'imageUrl' => $service->image_url,
            'description' => $service->description,
            'meta' => $service->duration_minutes . ' mins',
            'cta' => 'Book in the App',
        ]);
    }

    public function promo($id)
    {
        $promo = Promo::with('service')->where('is_active', true)->findOrFail($id);

        return view('catalog.detail', [
            'type' => 'promo',
            'title' => $promo->title,
            'category' => 'Limited offer',
            'price' => $promo->price,
            'imageUrl' => $promo->image_url,
            'description' => $promo->service ? 'Includes: ' . $promo->service->name : null,
            'meta' => null,
            'cta' => 'Claim in the App',
        ]);
    }
}
