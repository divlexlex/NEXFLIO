<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\ServiceCatalog;
use Illuminate\Http\Request;

class ServicesController extends Controller
{
    public function index(Request $request)
    {
        $servicesByCategory = ServiceCatalog::activeGroupedByCategory();

        return view('services.index', [
            'servicesByCategory' => $servicesByCategory,
            // Deep-link support for the Home page's category rail
            // (landing/index.blade.php) — falls back to "All" when absent
            // or when the category no longer has any active services.
            'activeCategory' => $servicesByCategory->has($request->query('category'))
                ? $request->query('category')
                : null,
        ]);
    }
}
