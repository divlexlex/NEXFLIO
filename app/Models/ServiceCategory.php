<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * A service category managers can extend from the Services page (replaces
 * the hardcoded App\Support\ServiceCatalog::CATEGORY_ORDER list as the
 * source of truth). The `name` is what services.category stores. Row order
 * (id) is the display order on the admin form/filter and the public
 * Services page.
 */
class ServiceCategory extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
    ];

    /**
     * Category names in seed order (id-ordered) — the single ordering source
     * for the admin selects and App\Support\ServiceCatalog.
     *
     * @return string[]
     */
    public static function names(): array
    {
        return static::query()
            ->orderBy('id')
            ->pluck('name')
            ->all();
    }
}
