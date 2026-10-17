<?php

namespace App\Support;

use App\Models\Service;
use App\Models\ServiceCategory;
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
     * Legacy category tab order, kept as the ServiceCategorySeeder source
     * and as the fallback when the service_categories table has no rows
     * (see categoryOrder()). Anything not listed here (e.g. a new category
     * added in the database) is appended after these, sorted alphabetically,
     * so the tabs never look arbitrary.
     */
    public const CATEGORY_ORDER = [
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

    /**
     * The five package services — ordinary Service rows currently grouped
     * under the 'Head Spa' category, surfaced as their own "Packages" tab in
     * the services sidebar (partials/services-section.blade.php). This is a
     * VIEW-level grouping only: these Services keep their real admin-set
     * category (seeders / config/packages.php are untouched) and simply also
     * appear in this extra tab. Single source of truth for "what counts as a
     * package service" — the sidebar reads this list, nothing else.
     */
    public const PACKAGE_SERVICE_NAMES = [
        'Package EXA',
        'Package DEE',
        'Package SAM',
        'Package LIZ',
        'Perfect Package',
    ];

    /**
     * The live category order: the service_categories table (seed order =
     * id order) when it has rows, otherwise the legacy CATEGORY_ORDER
     * constant as a fallback. This is what both the admin selects and the
     * public grouping read, so a category added in the admin shows up on the
     * client side with no code change.
     *
     * @return string[]
     */
    public static function categoryOrder(): array
    {
        $names = ServiceCategory::names();

        return $names !== [] ? $names : self::CATEGORY_ORDER;
    }

    public static function activeGroupedByCategory(): Collection
    {
        $order = static::categoryOrder();

        return static::active()
            ->groupBy('category')
            ->sortBy(function ($services, string $category) use ($order) {
                $position = array_search($category, $order, true);

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
                'package_details' => $service->package_details,
            ]);
    }
}
