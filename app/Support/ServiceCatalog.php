<?php

namespace App\Support;

use App\Models\Service;
use Illuminate\Support\Collection;

/**
 * Read-shape for the public Services catalog (Guest Home + the dedicated
 * /services page). Every item has the same array shape — ['id', 'name',
 * 'category', 'price', 'duration_minutes', 'image_url', 'badge',
 * 'description'] — so views never need to branch on where a service came
 * from.
 *
 * The catalog is entirely real, Admin-managed Service rows (see
 * database/seeders/ServiceSeeder.php) with real images on the storage disk.
 */
class ServiceCatalog
{
    /**
     * Preferred category tab order. Anything not listed here (e.g. a new
     * category added only in the database) is appended after these, sorted
     * alphabetically, so the tabs never look arbitrary.
     */
    private const CATEGORY_ORDER = [
        'Facial', 'Massage', 'Nails', 'Lashes & Brows', 'Aesthetics', 'Head Spa', 'Home Service',
    ];

    /**
     * One Bootstrap Icon per known category — shared by the Home page's
     * category rail (landing/index.blade.php) and the /services sidebar
     * (partials/services-section.blade.php) so the two never drift apart.
     * A category not listed here (e.g. a brand-new one added only in the
     * database) just renders without an icon rather than a wrong guess.
     */
    public const CATEGORY_ICONS = [
        'Facial' => 'bi-droplet-half',
        'Massage' => 'bi-flower1',
        'Nails' => 'bi-hand-index-thumb',
        'Lashes & Brows' => 'bi-eye',
        'Aesthetics' => 'bi-stars',
        'Head Spa' => 'bi-water',
        'Home Service' => 'bi-house-heart',
    ];

    public static function activeGroupedByCategory(): Collection
    {
        return static::active()
            ->groupBy('category')
            ->sortBy(function ($services, string $category) {
                $position = array_search($category, self::CATEGORY_ORDER, true);

                return $position === false ? [1, $category] : [0, $position];
            });
    }

    public static function active(): Collection
    {
        return Service::where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'category' => $service->category,
                'price' => $service->price,
                'duration_minutes' => $service->duration_minutes,
                'image_url' => $service->image_url,
                'badge' => $service->badge,
                'description' => $service->description,
            ]);
    }
}
