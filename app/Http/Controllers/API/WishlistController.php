<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        return Wishlist::where('user_id', $request->user()->id)
            ->with('service')
            ->get();
    }

    public function store(Request $request, $serviceId)
    {
        $wishlist = Wishlist::firstOrCreate([
            'user_id' => $request->user()->id,
            'service_id' => $serviceId,
        ]);

        return $wishlist->load('service');
    }

    public function destroy(Request $request, $serviceId)
    {
        Wishlist::where('user_id', $request->user()->id)
            ->where('service_id', $serviceId)
            ->delete();

        return response()->json(['message' => 'Removed from wishlist.']);
    }
}
