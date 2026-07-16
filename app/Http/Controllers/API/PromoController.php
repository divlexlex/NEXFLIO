<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Promo;

class PromoController extends Controller
{
    public function index()
    {
        return Promo::where('is_active', true)->with('service')->get();
    }
}
