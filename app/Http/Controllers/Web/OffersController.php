<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Promo;

/**
 * Public "Special Offers" listing (/offers) — the full, deterministic
 * counterpart to the Home page teaser (see landing/index.blade.php's Special
 * Offers section, which shows a 4-item slice of the same query). Both share
 * partials.offers-section for the actual card grid.
 */
class OffersController extends Controller
{
    public function index()
    {
        return view('offers.index', [
            'promos' => Promo::live()->with('services')->latest()->get(),
        ]);
    }
}
