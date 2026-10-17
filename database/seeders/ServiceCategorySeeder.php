<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use App\Support\ServiceCatalog;
use Illuminate\Database\Seeder;

/**
 * The canonical service categories: the classic CATEGORY_ORDER list plus the
 * new "Packages" category, last. Idempotent (firstOrCreate), so re-running
 * never duplicates and never disturbs manager additions or row order.
 *
 * App\Support\ServiceCatalog::CATEGORY_ORDER stays the source/fallback for
 * this list — do not delete it.
 */
class ServiceCategorySeeder extends Seeder
{
    /**
     * @return string[]
     */
    public static function categories(): array
    {
        return array_merge(ServiceCatalog::CATEGORY_ORDER, ['Packages']);
    }

    public function run(): void
    {
        foreach (self::categories() as $name) {
            ServiceCategory::query()->firstOrCreate(['name' => $name]);
        }
    }
}
