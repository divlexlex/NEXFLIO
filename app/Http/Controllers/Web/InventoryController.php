<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function index(Request $request)
    {
        // Category sources are the table (id order keeps the classics
        // first); the requested value resolves case-insensitively so old
        // lowercase links keep working, falling back to the first one.
        $categories = InventoryCategory::orderBy('id')->pluck('name')->all();

        $category = InventoryCategory::canonicalize($request->get('category'))
            ?? ($categories[0] ?? null);

        $q = trim((string) $request->query('q', ''));
        $stock = $request->query('stock', 'all');
        if (! in_array($stock, ['all', 'in', 'low', 'out'], true)) {
            $stock = 'all';
        }

        // Stock definitions mirror the page's "Low" badge rule
        // (quantity <= reorder_point): In = above the point, Low = at or
        // below it (0 included, exactly like the badge), Out = zero.
        // Category matching is case-insensitive so legacy lowercase rows
        // stay listed even where the normalizing seeder has not run.
        $itemsQuery = Inventory::with(['batches' => function ($query) {
            $query->where('quantity_remaining', '>', 0)
                ->orderBy('received_at')
                ->orderBy('id');
        }])
            ->when($category !== null, fn ($query) => $query->whereRaw('LOWER(category) = ?', [mb_strtolower($category)]))
            ->when($q !== '', fn ($query) => $query->where('item_name', 'like', '%'.$q.'%'))
            ->when($stock === 'in', fn ($query) => $query->whereColumn('quantity', '>', 'reorder_point'))
            ->when($stock === 'low', fn ($query) => $query->whereColumn('quantity', '<=', 'reorder_point'))
            ->when($stock === 'out', fn ($query) => $query->where('quantity', 0))
            ->orderBy('item_name');

        $totalInCategory = $category === null
            ? 0
            : Inventory::whereRaw('LOWER(category) = ?', [mb_strtolower($category)])->count();

        return view('admin.inventory.index', [
            'category' => $category,
            'categories' => $categories,
            'items' => $itemsQuery->paginate(10)->withQueryString(),
            'totalInCategory' => $totalInCategory,
            'currentQ' => $q,
            'currentStock' => $stock,
            'filtersActive' => $q !== '' || $stock !== 'all',
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
            'category' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'reorder_point' => 'required|integer|min:0',
            'price_per_unit' => 'required|numeric|min:0',
            'initial_quantity' => 'nullable|integer|min:1',
        ]);

        $category = InventoryCategory::canonicalize($validated['category']);

        if ($category === null) {
            throw ValidationException::withMessages(['category' => 'Please select a valid category from the list.']);
        }

        $inventory = Inventory::create([
            'item_name' => $validated['item_name'],
            'category' => $category,
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

    /**
     * Edit an item's master data (name, category, unit, price, reorder
     * point). On-hand quantity and FIFO batches are never touched here —
     * stock moves only via Receive / Pull Out, so no movement rows are
     * written and history is undisturbed.
     */
    public function update(Request $request, $id)
    {
        $item = Inventory::findOrFail((int) $id);

        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'reorder_point' => 'required|integer|min:0',
            'price_per_unit' => 'required|numeric|min:0',
        ]);

        $category = InventoryCategory::canonicalize($validated['category']);

        if ($category === null) {
            throw ValidationException::withMessages(['category' => 'Please select a valid category from the list.']);
        }

        $item->update([
            'item_name' => $validated['item_name'],
            'category' => $category,
            'unit' => $validated['unit'],
            'reorder_point' => $validated['reorder_point'],
            'price_per_unit' => $validated['price_per_unit'],
        ]);

        return back()->with('success', 'Item updated.');
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
