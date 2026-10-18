<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * A staff position managers can extend from the Employees page (replaces
 * the fixed App\Enums\Position list as the source of truth). The `name` is
 * what staff_profiles.position stores; `service_category` (nullable) links
 * the position to the service category it can fulfil for booking
 * personnel eligibility. Categories with no linked positions have no
 * eligible staff in admin pickers.
 */
class Position extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'service_category',
    ];

    /**
     * Position names fulfilling a service category, id-ordered (classic
     * positions first). Empty when nothing is linked — admin staff pickers
     * treat that as “no eligible employees” (empty dropdown).
     *
     * @return string[]
     */
    public static function namesForServiceCategory(string $category): array
    {
        return static::query()
            ->where('service_category', $category)
            ->orderBy('id')
            ->pluck('name')
            ->all();
    }

    /** Packages (and any future multi-position services) that list this role. */
    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_positions')->withTimestamps();
    }
}
