<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasImage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promo extends Model
{
    use Auditable, HasFactory, HasImage, SoftDeletes;

    protected $fillable = [
        'title',
        'image_path',
        'price',
        'service_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['image_url'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
