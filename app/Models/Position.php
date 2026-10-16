<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * A staff position managers can extend from the Employees page (replaces
 * the fixed App\Enums\Position list as the source of truth). The `name` is
 * what staff_profiles.position stores; `service_category` (nullable) links
 * the position to the service category it can fulfil for booking
 * personnel eligibility — unlinked positions impose no category filter.
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
     * positions first). Empty when nothing is linked — callers keep their
     * no-filter behaviour in that case.
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
}
