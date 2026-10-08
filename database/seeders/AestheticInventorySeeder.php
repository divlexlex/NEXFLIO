<?php

namespace Database\Seeders;

use App\Models\Inventory;
use Illuminate\Database\Seeder;

/**
 * The 32 consumable/tooling items that belong to the Inventory page's
 * "Aesthetic" tab (see App\Http\Controllers\Web\InventoryController and
 * resources/views/admin/inventory/index.blade.php).
 *
 * Same contract as InventorySeeder (Nails) / MassageInventorySeeder:
 *
 *  - Keyed on item_name AND category together, soft-deleted rows included, so
 *    an item that already exists in another category is still created here as
 *    its own row. Alcohol, Wipes and Gloves exist under 'nails' and/or
 *    'massage' and are deliberately created again under 'aesthetic' — never
 *    merged, moved or skipped.
 *  - "Alcohol" is ONE item within the aesthetic category (its single row
 *    covers the LASHES / WAXING / GLUTA paper sheets), so it appears exactly
 *    once in this list.
 *  - Safe to re-run: an existing aesthetic row is never duplicated and its
 *    quantity/price edits made later in the Admin UI are never overwritten.
 *  - quantity 0 (the project's default for a new item created without an
 *    opening-stock count), reorder_point 3 (the New Item form's default),
 *    unit/price from the shared defaults below; staff top up stock via
 *    Receive.
 */
class AestheticInventorySeeder extends Seeder
{
    private const CATEGORY = 'aesthetic';

    public function run(): void
    {
        foreach ($this->aestheticItems() as $itemName) {
            Inventory::withTrashed()->firstOrCreate(
                ['item_name' => $itemName, 'category' => self::CATEGORY],
                [
                    'unit' => 'pc',
                    'quantity' => 0,
                    'reorder_point' => 3,
                    'price_per_unit' => 0,
                ]
            );
        }
    }

    /**
     * Exact names and order as supplied for the Aesthetic tab — do not correct
     * spelling or re-order. Grouped by the business's paper sheets (LASHES /
     * WAXING / GLUTA); the grouping is only for readability.
     *
     * @return list<string>
     */
    private function aestheticItems(): array
    {
        return [
            // LASHES
            'Lash Extension 9',
            'Lash Extension 10',
            'Lash Extension 11',
            'Lash Extension 12',
            'Lash Extension 13',
            'Lash Extension 14',
            'Lash Extension Mix',
            'Lash Glue',
            'Lash Tape',
            'Lash Patches',
            'Brow Tint',
            'Alcohol',
            // WAXING
            'Wipes',
            'Hard Wax',
            'Soft Wax',
            'Cotton',
            'Wax Strips',
            'Powder',
            'Post Wax Oil',
            'Pattern Paper',
            'Gloves',
            'Feminine Wash',
            'Scissors',
            'Tweezers',
            // GLUTA
            'Glutax Pink',
            'Glutax Blue',
            'Saluta',
            'L-Carnitine',
            'Vitamin C',
            'B-Complex',
            'Collagen',
            'Placenta',
        ];
    }
}