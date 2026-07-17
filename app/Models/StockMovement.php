<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only stock ledger. Rows are written by InventoryService and can
 * never be modified or removed — corrections are new 'adjustment' rows.
 */
class StockMovement extends Model
{
    public const TYPE_IN = 'in';
    public const TYPE_OUT = 'out';
    public const TYPE_PULL_OUT = 'pull_out';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'inventory_id',
        'inventory_batch_id',
        'type',
        'quantity',
        'unit_cost',
        'reference_type',
        'reference_id',
        'user_id',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Stock movements are immutable.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Stock movements are immutable.');
        });
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function batch()
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
