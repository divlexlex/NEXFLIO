<?php

namespace Database\Seeders;

use App\Models\Promo;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Real promos sourced from Perfect Nails' own Facebook page
 * (facebook.com/PerfectNailsWellness). Seeded as drafts (`is_active` =
 * false) — visible to Admin/Manager on the Promos page, but never shown on
 * the public Home teaser or /offers page until reviewed, given a real flyer
 * image, and switched on (see App\Http\Controllers\Web\PromoController).
 */
class PromoSeeder extends Seeder
{
    public function run(): void
    {
        $wartsRemoval = Service::where('name', 'Warts Removal (Per Area)')->first();
        $packageExa = Service::where('name', 'Package EXA')->first();
        $whiteningScrub = Service::where('name', 'Whitening Body Scrub')->first();
        $manicure = Service::where('name', 'Manicure')->first();
        $pedicure = Service::where('name', 'Pedicure')->first();

        $wartsPromo = Promo::updateOrCreate(
            ['title' => 'Warts Removal Treatment'],
            [
                'discount_type' => Promo::TYPE_BUNDLE,
                'price' => 799,
                'is_active' => false,
            ]
        );
        $wartsPromo->services()->sync(array_filter([$wartsRemoval?->id]));

        $wednesdayPromo = Promo::updateOrCreate(
            ['title' => 'Wednesday Promo — Package EXA Solo'],
            [
                'description' => 'Every Wednesday only — 30-min Japanese Head Spa + Basic Facial Treatment.',
                'discount_type' => Promo::TYPE_FIXED_AMOUNT,
                'discount_value' => 200,
                'price' => null,
                'is_active' => false,
            ]
        );
        $wednesdayPromo->services()->sync(array_filter([$packageExa?->id]));

        $midYearPromo = Promo::updateOrCreate(
            ['title' => 'Mid Year Promo — Whitening Body Scrub'],
            [
                'description' => 'Brightens and evens out skin tone, exfoliates dead skin cells, and nourishes and hydrates for soft, glowing skin.',
                'discount_type' => Promo::TYPE_FIXED_AMOUNT,
                'discount_value' => 100,
                'price' => null,
                'is_active' => false,
            ]
        );
        $midYearPromo->services()->sync(array_filter([$whiteningScrub?->id]));

        $pamperPromo = Promo::updateOrCreate(
            ['title' => 'Pamper All You Can'],
            [
                // Bundles several treatments (not all of which have their own
                // catalog entry — Foot Scrub, Whitening Foot Cream, Foot
                // Paraffin) at one flat price, so this is a 'bundle' promo.
                // The description carries the full inclusions/add-ons list;
                // only the catalog items that do exist are linked below.
                'description' => 'Includes: Head Spa, Basic Facial, Manicure, Pedicure, Basic Foot Spa, Foot Scrub, Whitening Foot Cream, and Foot Paraffin. '
                    .'Save ₱1,560 off à la carte pricing. Add-ons available: Gel Manicure ₱399, Gel Pedicure ₱399, Softgel ₱599, Carbon Laser ₱699.',
                'discount_type' => Promo::TYPE_BUNDLE,
                'price' => 1999,
                'is_active' => false,
            ]
        );
        $pamperPromo->services()->sync(array_filter([$manicure?->id, $pedicure?->id]));
    }
}
