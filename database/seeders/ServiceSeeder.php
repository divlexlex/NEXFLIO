<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Real Service catalog for Perfect Nails — every field here (name, category,
 * price, duration) is sourced from Perfect Nails' own approved price-list
 * references. This is the single
 * source of truth now: everything here is a real, bookable, Admin-editable
 * `services` row — see App\Support\ServiceCatalog for how the public
 * Services page reads it, and App\Http\Controllers\Web\ServiceController for
 * how Admin edits it.
 *
 * Trimmed to the owner's exact KEEP list (24 services). Every other row that
 * used to live here was removed from the current database via the one-off
 * ServiceCatalogTrimSeeder; the owner will re-add any other treatments later
 * through the Admin "New Service" form.
 *
 * Images: this project has no per-treatment product photography yet, so each
 * category is seeded with a representative photo from the site's existing
 * gallery/hero-slider assets (public/images/...), copied onto the public
 * storage disk exactly like an Admin-uploaded image would be (see
 * copyToPublicDisk()) — image_path/image_url work identically either way.
 * Replace any of these via the Admin Services page once real photos exist.
 */
class ServiceSeeder extends Seeder
{
    /** category => [default image, ...alternates for variety in a large category] */
    private const CATEGORY_IMAGES = [
        'Facial' => ['images/hero-slider/facial-treatment.jpg'],
        'Massage' => ['images/hero-slider/massage-wellness.jpg', 'images/gallery/massage-hot-stone.jpg'],
        'Nails' => ['images/hero-slider/nail-care.jpg', 'images/gallery/nails-pedicure.jpg'],
        'Lashes & Brows' => ['images/hero-slider/facial-treatment.jpg'],
        'Aesthetics' => ['images/gallery/aesthetics-led-1.jpg', 'images/gallery/aesthetics-led-2.jpg'],
        'Head Spa' => ['images/gallery/headspa-rinse-1.jpg', 'images/gallery/headspa-rinse-2.jpg'],
        'Home Service' => ['images/hero-slider/massage-wellness.jpg'],
    ];

    public function run(): void
    {
        // Upserts by name — safe to re-run any time (e.g. adding a new
        // treatment to the catalog() list below) without disturbing existing
        // rows' ids, which real data now depends on (Promos link to a
        // Service by id — see PromoSeeder). An earlier version of this
        // seeder deleted-then-recreated everything on every run; that was
        // fine only while nothing referenced a Service yet.
        $imageCache = [];
        $index = ['Massage' => 0, 'Nails' => 0, 'Aesthetics' => 0, 'Head Spa' => 0];

        foreach ($this->catalog() as $entry) {
            $category = $entry['category'];
            $variants = self::CATEGORY_IMAGES[$category] ?? [];
            $variant = $variants[($index[$category] ?? 0) % max(count($variants), 1)] ?? null;
            if (array_key_exists($category, $index)) {
                $index[$category]++;
            }

            $existing = Service::where('name', $entry['name'])->first();
            $imagePath = $existing?->image_path ?? ($variant ? ($imageCache[$variant] ??= $this->copyToPublicDisk($variant)) : null);

            Service::updateOrCreate(
                ['name' => $entry['name']],
                [
                    'category' => $category,
                    'description' => $entry['description'] ?? null,
                    'price' => $entry['price'],
                    'duration_minutes' => $entry['duration_minutes'],
                    'service_location_type' => $category === 'Home Service' ? 'home' : 'branch',
                    'status' => 'active',
                    'badge' => $entry['badge'] ?? null,
                    'image_path' => $imagePath,
                ]
            );
        }
    }

    /**
     * Copies a bundled public/images asset onto the public storage disk
     * (storage/app/public/services/...) so it's referenced through
     * `image_path` exactly like a real Admin upload — same disk, same
     * `image_url` accessor, no special-casing anywhere else in the app.
     */
    private function copyToPublicDisk(string $sourceRelativeToPublic): string
    {
        $destination = 'services/'.basename($sourceRelativeToPublic);

        if (! Storage::disk('public')->exists($destination)) {
            Storage::disk('public')->put(
                $destination,
                File::get(public_path($sourceRelativeToPublic))
            );
        }

        return $destination;
    }

    private function catalog(): array
    {
        return [
            // ---- Facial ------------------------------------------------------
            ['name' => 'Basic Facial', 'category' => 'Facial', 'price' => 349, 'duration_minutes' => 45],
            ['name' => 'Signature Facial', 'category' => 'Facial', 'price' => 599, 'duration_minutes' => 60],
            ['name' => 'Hydra Facial', 'category' => 'Facial', 'price' => 999, 'duration_minutes' => 60, 'badge' => 'Best'],

            // ---- Massage -------------------------------------------------------
            ['name' => 'Swedish Massage', 'category' => 'Massage', 'price' => 599, 'duration_minutes' => 60, 'badge' => 'Most Booked'],
            ['name' => 'Hot Stone Massage', 'category' => 'Massage', 'price' => 799, 'duration_minutes' => 60],
            ['name' => 'Aromatherapy (Herbal Ball)', 'category' => 'Massage', 'price' => 799, 'duration_minutes' => 60],

            // ---- Nails ---------------------------------------------------------
            ['name' => 'Manicure', 'category' => 'Nails', 'price' => 179, 'duration_minutes' => 30],
            ['name' => 'Pedicure', 'category' => 'Nails', 'price' => 199, 'duration_minutes' => 45],
            ['name' => 'Gel Manicure', 'category' => 'Nails', 'price' => 399, 'duration_minutes' => 60],

            // ---- Lashes & Brows --------------------------------------------------
            ['name' => 'Classic Lashes', 'category' => 'Lashes & Brows', 'price' => 349, 'duration_minutes' => 90],
            ['name' => 'Lash Lift', 'category' => 'Lashes & Brows', 'price' => 349, 'duration_minutes' => 60],
            ['name' => 'Brow Lamination', 'category' => 'Lashes & Brows', 'price' => 349, 'duration_minutes' => 45],

            // ---- Aesthetics ----------------------------------------------------
            ['name' => 'BB Glow', 'category' => 'Aesthetics', 'price' => 599, 'duration_minutes' => 45],
            ['name' => 'Warts Removal (Per Area)', 'category' => 'Aesthetics', 'price' => 799, 'duration_minutes' => 20],
            ['name' => 'Whitening Body Scrub', 'category' => 'Aesthetics', 'price' => 799, 'duration_minutes' => 45],

            // ---- Head Spa Packages -----------------------------------------------
            // Price shown is the Solo tier; Couple/Barkada pricing is in the description.
            ['name' => 'Package EXA', 'category' => 'Head Spa', 'price' => 999, 'duration_minutes' => 30,
                'description' => 'Solo package — 30-min Japanese Head Spa + Facial Treatment. Couple ₱1,699 · Barkada ₱3,299.'],
            ['name' => 'Package DEE', 'category' => 'Head Spa', 'price' => 1599, 'duration_minutes' => 30,
                'description' => 'Solo package — Head Spa + Facial Treatment + 1-hour Whole Body Massage. Couple ₱2,999 · Barkada ₱5,899.'],
            ['name' => 'Package SAM', 'category' => 'Head Spa', 'price' => 2299, 'duration_minutes' => 30,
                'description' => 'Solo package — Head Spa + Facial + Swedish Massage + Whitening Body Scrub. Couple ₱4,399 · Barkada ₱8,599.'],
            ['name' => 'Perfect Package', 'category' => 'Head Spa', 'price' => 2599, 'duration_minutes' => 30, 'badge' => 'Most Booked',
                'description' => 'Solo package — Head Spa + Facial + Swedish Massage + Body Scrub + Foot Spa + Pedicure. Couple ₱4,999 · Barkada ₱9,799.'],
            ['name' => 'Package LIZ', 'category' => 'Head Spa', 'price' => 1899, 'duration_minutes' => 30,
                'description' => 'Solo package — Head Spa + Facial + Foot Spa + Pedicure. Couple ₱3,699 · Barkada ₱7,199.'],
            ['name' => 'Kiddie Japanese Head Spa', 'category' => 'Head Spa', 'price' => 599, 'duration_minutes' => 30, 'badge' => 'New',
                'description' => 'Safe and gentle for kids ages 5–12. Includes mini facial (gentle cleanse & massage), shampoo & conditioning, and water-surfing neck and scalp play.'],

            // ---- Home Service (minimum 90 mins per booking, per reference pricelist) --
            ['name' => 'Home Service — Swedish Massage', 'category' => 'Home Service', 'price' => 899, 'duration_minutes' => 90],
            ['name' => 'Home Service — Hot Stone Massage', 'category' => 'Home Service', 'price' => 1199, 'duration_minutes' => 90],
            ['name' => 'Home Service — Perfect Signature Massage', 'category' => 'Home Service', 'price' => 1499, 'duration_minutes' => 120, 'badge' => 'New'],
        ];
    }
}
