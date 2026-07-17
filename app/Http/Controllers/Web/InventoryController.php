<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function index()
    {
        return view('admin.inventory.index', [
            'items' => Inventory::with(['batches' => function ($query) {
                $query->where('quantity_remaining', '>', 0)
                    ->orderBy('received_at')
                    ->orderBy('id');
            }])->orderBy('item_name')->get(),
            'recentMovements' => StockMovement::with(['inventory:id,item_name', 'user:id,name'])
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'reorder_point' => 'required|integer|min:0',
            'price_per_unit' => 'required|numeric|min:0',
            'initial_quantity' => 'nullable|integer|min:1',
        ]);

        $inventory = Inventory::create([
            'item_name' => $validated['item_name'],
            'unit' => $validated['unit'],
            'quantity' => 0,
            'reorder_point' => $validated['reorder_point'],
            'price_per_unit' => $validated['price_per_unit'],
        ]);

        if (! empty($validated['initial_quantity'])) {
            $this->inventoryService->receive(
                $inventory->id,
                $validated['initial_quantity'],
                (float) $validated['price_per_unit'],
                $request->user()->id,
                'Opening stock'
            );
        }

        return back()->with('success', 'Item added.');
    }

    public function addBatch(Request $request, $id)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'unit_cost' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:255',
        ]);

        $this->inventoryService->receive(
            (int) $id,
            $validated['quantity'],
            (float) $validated['unit_cost'],
            $request->user()->id,
            $validated['reason'] ?? null
        );

        return back()->with('success', 'Stock received.');
    }

    public function pullOut(Request $request, $id)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
        ]);

        $this->inventoryService->consume(
            (int) $id,
            $validated['quantity'],
            StockMovement::TYPE_PULL_OUT,
            null,
            $request->user()->id,
            $validated['reason']
        );

        return back()->with('success', 'Stock pulled out.');
    }
}
