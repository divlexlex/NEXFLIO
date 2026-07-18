<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;

class ProductController extends Controller
{
    // Active retail products for the app's Shop.
    public function index()
    {
        return Product::where('status', 'active')->orderBy('name')->get();
    }

    // Single product for the detail page.
    public function show($id)
    {
        return Product::findOrFail($id);
    }
}
