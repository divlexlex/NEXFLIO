<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * An inventory category managers can extend from the Inventory page
 * (replaces the hardcoded facial/massage/nails/aesthetic list as the
 * source of truth). The `name` is what inventories.category stores.
 */
class InventoryCategory extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
    ];

    /**
     * Resolve a submitted value to its canonical stored name,
     * case-insensitively (old lowercase values keep working). Null when
     * nothing in the table matches.
     */
    public static function canonicalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return static::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
            ->value('name');
    }
}
