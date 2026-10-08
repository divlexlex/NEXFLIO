<?php

namespace Database\Seeders;

use App\Models\Inventory;
use Illuminate\Database\Seeder;

/**
 * The 45 consumable/tooling items that belong to the Inventory page's "Nails"
 * tab (see App\Http\Controllers\Web\InventoryController::$validCategories and
 * resources/views/admin/inventory/index.blade.php).
 *
 * There is no other inventory source in this project — items are normally
 * created one-by-one through the Admin "+ New Item" form — so this seeder is
 * the reproducible definition of this batch.
 *
 * Safe to re-run: each row is created only when its item_name does not exist
 * yet (including soft-deleted rows), so stock/price edits made later in the
 * Admin UI are never overwritten. quantity stays 0 (the project's default for
 * a new item created without an opening-stock count) and reorder_point uses
 * the New Item form's default of 3; staff top up stock via Receive.
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->nailsItems() as $itemName) {
            Inventory::withTrashed()->firstOrCreate(
                ['item_name' => $itemName],
                [
                    'category' => 'nails',
                    'unit' => 'pc',
                    'quantity' => 0,
                    'reorder_point' => 3,
                    'price_per_unit' => 0,
                ]
            );
        }
    }

    /**
     * Exact names and order as supplied for the Nails tab — do not correct
     * spelling or re-order.
     *
     * @return list<string>
     */
    private function nailsItems(): array
    {
        return [
            'Foot Scrub',
            'Foot Soak',
            'Alcohol',
            'Cuticle Remover',
            'Acetone',
            'Callus Remover',
            'Foot Blush',
            'Nail Cutter',
            'JL Glue',
            'Top Coat',
            'Magic Removal',
            'OMG Top Coat',
            'Buffer',
            'Nail File Violet',
            'Nail File Kosia',
            'Nail Brush',
            'Softgel Extension',
            'Foot Towel',
            'Hand Towel',
            'Orange Tissue',
            'Nail Pads',
            'Gel Primer',
            'Foot File',
            'Basin',
            'Pitchel',
            'UV Light',
            'Flash Cure',
            'Nail Drill',
            'Nail Brushes',
            'Extensions',
            'Garbage Bag',
            'Green Tissue',
            'Wipes',
            'Bleach',
            'Color Safe',
            'Sugar',
            'Coffee',
            'Zonrox',
            'Solvent',
            'Possy Red',
            'Cat Eye Gel Red',
            'Born Pretty',
            'Monja',
            'Colure',
            'Creamer',
        ];
    }
}
