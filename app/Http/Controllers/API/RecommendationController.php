<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Promo;
use App\Models\Service;
use Illuminate\Http\Request;

/**
 * Dynamic recommendations: a randomized mix of active services, products, and
 * promos. Public (works for guests and signed-in users) and re-shuffled on
 * every request, so the client just re-fetches to refresh.
 */
class RecommendationController extends Controller
{
    public function index(Request $request)
    {
        $limit = (int) $request->query('limit', 8);
        $limit = max(1, min($limit, 20));

        $services = Service::where('status', 'active')->get()->map(fn ($s) => [
            'type' => 'service',
            'id' => $s->id,
            'title' => $s->name,
            'subtitle' => $s->category,
            'price' => $s->price,
            'image_url' => $s->image_url,
        ]);

        $products = Product::where('status', 'active')->get()->map(fn ($p) => [
            'type' => 'product',
            'id' => $p->id,
            'title' => $p->name,
            'subtitle' => $p->category,
            'price' => $p->price,
            'image_url' => $p->image_url,
        ]);

        $promos = Promo::where('is_active', true)->with('service')->get()->map(fn ($p) => [
            'type' => 'promo',
            'id' => $p->id,
            'title' => $p->title,
            'subtitle' => $p->service?->name,
            'price' => $p->price,
            'image_url' => $p->image_url,
        ]);

        return $services
            ->concat($products)
            ->concat($promos)
            ->shuffle()
            ->take($limit)
            ->values();
    }
}
