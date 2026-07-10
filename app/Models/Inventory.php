<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = [
    'item_name', 
    'unit', 
    'quantity', 
    'reorder_point', 
    'price_per_unit'
];
}
