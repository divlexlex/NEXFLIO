<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Promo;

class PromoController extends Controller
{
    public function index()
    {
        return Promo::live()->with('services')->get();
    }

    public function show($id)
    {
        return Promo::with('services')->findOrFail($id);
    }
}
