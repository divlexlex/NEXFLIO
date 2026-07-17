<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class InventoryBatch extends Model
{
    use Auditable;

    protected $fillable = [
        'inventory_id',
        'quantity_received',
        'quantity_remaining',
        'unit_cost',
        'received_at',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
