<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventory extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'item_name',
        'unit',
        'quantity',
        'reorder_point',
        'price_per_unit',
    ];

    protected function casts(): array
    {
        return [
            'price_per_unit' => 'decimal:2',
        ];
    }

    public function batches()
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }
}
