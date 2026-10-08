<?php

namespace Database\Seeders;

use App\Models\Inventory;
use Illuminate\Database\Seeder;

/**
 * The 26 consumable/tooling items that belong to the Inventory page's
 * "Massage" tab (see App\Http\Controllers\Web\InventoryController and
 * resources/views/admin/inventory/index.blade.php).
 *
 * Same contract as InventorySeeder (Nails):
 *
 *  - Keyed on item_name AND category together, soft-deleted rows included, so
 *    an item that already exists in another category is still created here as
 *    its own row. Alcohol, Wipes and Orange Tissue exist under 'nails' and are
 *    deliberately created again under 'massage' — never merged, moved or
 *    skipped.
 *  - Facial Milky Wash and Facial Scrub stay under 'massage' where the
 *    official inventory sheet lists them (they are NOT moved to 'facial').
 *  - Safe to re-run: an existing massage row is never duplicated and its
 *    quantity/price edits made later in the Admin UI are never overwritten.
 *  - quantity 0 (the project's default for a new item created without an
 *    opening-stock count), reorder_point 3 (the New Item form's default),
 *    unit/price from the shared defaults below; staff top up stock via
 *    Receive.
 */
class MassageInventorySeeder extends Seeder
{
    private const CATEGORY = 'massage';

    public function run(): void
    {
        foreach ($this->massageItems() as $itemName) {
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
     * Exact names and order as supplied for the Massage tab — do not correct
     * spelling or re-order.
     *
     * @return list<string>
     */
    private function massageItems(): array
    {
        return [
            'Bath Towel',
            'Head Towel',
            'Bath Robes',
            'Wrap Bath Robes',
            'Blanket',
            'Boxer Shorts',
            'Gloves',
            'Ear Candling',
            'Facial Milky Wash',
            'Facial Scrub',
            'Serum',
            'Conditioner',
            'Shampoo',
            'Oil',
            'Alcohol',
            'Wipes',
            'Face Mask',
            'Herbal Anti Stress',
            'Hot Stones',
            'Ventosa Glass',
            'Lighter',
            'Blower',
            'Hair Brush',
            'Wooden Tray',
            'Empty Bottle',
            'Orange Tissue',
        ];
    }
}
