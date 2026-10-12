<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceVariant;
use Illuminate\Database\Seeder;

/**
 * Saleable tiers for the Head Spa packages. The base `services.price` keeps
 * the Solo price (so the public price list and any legacy view still reads a
 * sensible number) while the booking flow charges the selected tier from
 * here. Upserts by (service, label) so re-running is safe and ids stay stable.
 */
class ServiceVariantSeeder extends Seeder
{
    /** Package name => [[label, price, guests], ...] in display order. */
    private const VARIANTS = [
        'Package EXA' => [
            ['Solo', 999, 1],
            ['Couple', 1699, 2],
            ['Barkada', 3299, null],
        ],
        'Package DEE' => [
            ['Solo', 1599, 1],
            ['Couple', 2999, 2],
            ['Barkada', 5899, null],
        ],
        'Package SAM' => [
            ['Solo', 2299, 1],
            ['Couple', 4399, 2],
            ['Barkada', 8599, null],
        ],
        'Perfect Package' => [
            ['Solo', 2599, 1],
            ['Couple', 4999, 2],
            ['Barkada', 9799, null],
        ],
        'Package LIZ' => [
            ['Solo', 1899, 1],
            ['Couple', 3699, 2],
            ['Barkada', 7199, null],
        ],
    ];

    public function run(): void
    {
        foreach (self::VARIANTS as $serviceName => $variants) {
            $service = Service::where('name', $serviceName)->first();

            if ($service === null) {
                continue;
            }

            foreach ($variants as $sort => [$label, $price, $guests]) {
                ServiceVariant::updateOrCreate(
                    ['service_id' => $service->id, 'label' => $label],
                    ['price' => $price, 'guests' => $guests, 'sort' => $sort]
                );
            }
        }
    }
}
