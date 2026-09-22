<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\Service;

/**
 * Public detail pages for services and promos.
 *
 * For a Guest, the primary CTA still opens the "Get the app" / "Continue
 * on Website" choice modal (booking requires an account). For an
 * authenticated Client, catalog/detail.blade.php routes the CTA straight
 * into the real Website Booking Flow (App\Http\Controllers\Web\BookingController)
 * instead — see that view for the auth-aware logic. `id` and `locationType`
 * are exposed here so the view can build that destination (Branch, Home
 * Service, or the Branch/Home choice for a `both` service — see
 * App\Enums\ServiceLocationType) without querying the Service model again.
 */
class CatalogController extends Controller
{
    public function service($id)
    {
        $service = Service::where('status', 'active')->findOrFail($id);

        return view('catalog.detail', [
            'type' => 'service',
            'id' => $service->id,
            'title' => $service->name,
            'category' => $service->category,
            'locationType' => $service->service_location_type->value,
            'price' => $service->price,
            'imageUrl' => $service->image_url,
            'description' => $service->description,
            'meta' => $service->duration_minutes.' mins',
            'cta' => 'Book Now',
        ]);
    }

    public function promo($id)
    {
        $promo = Promo::live()->with('services')->findOrFail($id);
        $serviceNames = $promo->services->pluck('name')->implode(', ');

        return view('catalog.detail', [
            'type' => 'promo',
            'category' => 'Limited offer',
            'title' => $promo->title,
            'price' => $promo->displayPrice(),
            'imageUrl' => $promo->image_url,
            'description' => $promo->description ?? ($serviceNames ? 'Includes: '.$serviceNames : null),
            'meta' => $promo->discountLabel(),
            'cta' => 'Claim Offer',
        ]);
    }
}
