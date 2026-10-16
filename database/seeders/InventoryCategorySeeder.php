<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\InventoryCategory;
use Illuminate\Database\Seeder;

/**
 * The four classic inventory categories. Idempotent: re-running restores
 * any missing classic and normalizes the four legacy lowercase values
 * ('facial', …) left by the pre-table forms to their canonical names —
 * later manager edits to item rows are never overwritten.
 */
class InventoryCategorySeeder extends Seeder
{
    /**
     * @return array<int, array{name: string, legacy: string|null}>
     */
    public static function categories(): array
    {
        return [
            ['name' => 'Facial', 'legacy' => 'facial'],
            ['name' => 'Massage', 'legacy' => 'massage'],
            ['name' => 'Nails', 'legacy' => 'nails'],
            ['name' => 'Aesthetic', 'legacy' => 'aesthetic'],
        ];
    }

    public function run(): void
    {
        foreach (self::categories() as $category) {
            InventoryCategory::query()->firstOrCreate(['name' => $category['name']]);

            if ($category['legacy'] !== null) {
                Inventory::query()
                    ->where('category', $category['legacy'])
                    ->update(['category' => $category['name']]);
            }
        }
    }
}
