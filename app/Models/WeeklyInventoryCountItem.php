<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single line of a weekly count: the system snapshot taken when the count
 * opened and the quantity physically counted (null = not counted yet).
 * Never edited after the count is finalized.
 */
class WeeklyInventoryCountItem extends Model
{
    protected $fillable = [
        'weekly_inventory_count_id',
        'inventory_id',
        'system_quantity',
        'counted_quantity',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'integer',
            'counted_quantity' => 'integer',
        ];
    }

    public function count()
    {
        return $this->belongsTo(WeeklyInventoryCount::class, 'weekly_inventory_count_id');
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    /**
     * counted − system. Null while the line is still blank (not counted).
     */
    public function variance(): ?int
    {
        if ($this->counted_quantity === null) {
            return null;
        }

        return $this->counted_quantity - $this->system_quantity;
    }
}
