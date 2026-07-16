<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;

class GiftCardController extends Controller
{
    public function index()
    {
        return GiftCard::where('status', 'active')->get();
    }
}
