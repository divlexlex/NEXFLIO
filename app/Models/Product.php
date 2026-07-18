<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasImage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use Auditable, HasFactory, HasImage, SoftDeletes;

    protected $fillable = [
        'name',
        'category',
        'description',
        'price',
        'image_path',
        'stock',
        'status',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }
}
