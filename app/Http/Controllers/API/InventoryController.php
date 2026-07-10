<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Http\Request;

class InventoryController extends Controller
{

    public function index() { return Inventory::all(); }

    public function addStock(Request $request, $id) {
        $inventory = Inventory::findOrFail($id);
        $inventory->increment('quantity', $request->quantity);
        return response()->json(['message' => 'Stock added successfully', 'new_quantity' => $inventory->quantity]);
    }

    public function deductStock(Request $request, $id) {
        $inventory = Inventory::findOrFail($id);
        if ($inventory->quantity < $request->quantity) {
            return response()->json(['error' => 'Insufficient stock'], 400);
        }
        $inventory->decrement('quantity', $request->quantity);
        return response()->json(['message' => 'Stock deducted', 'new_quantity' => $inventory->quantity]);
    }
}