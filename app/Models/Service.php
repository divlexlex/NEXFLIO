<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasImage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use Auditable, HasFactory, HasImage, SoftDeletes;

    protected $fillable = [
        'name',
        'category',
        'description',
        'price',
        'duration_minutes',
        'image_path',
        'status',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
