<?php

namespace App\Models;

use App\Enums\ServiceLocationType;
use App\Traits\Auditable;
use App\Traits\HasImage;
use Illuminate\Database\Eloquent\Builder;
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
        'package_details',
        'price',
        'duration_minutes',
        'image_path',
        'status',
        'badge',
        'service_location_type',
        'available_from',
        'available_until',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'service_location_type' => ServiceLocationType::class,
        ];
    }

    /** Real, active services bookable at the branch (branch or both). */
    public function scopeBookableAtBranch(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereIn('service_location_type', ServiceLocationType::bookableAtBranch());
    }

    /** Real, active services bookable as Home Service (home or both). */
    public function scopeBookableAtHome(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereIn('service_location_type', ServiceLocationType::bookableAtHome());
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    /** Saleable tiers (Solo / Couple / Barkada); empty for regular services. */
    public function variants()
    {
        return $this->hasMany(ServiceVariant::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Staff positions that can cater this service. Used for Packages that
     * span multiple service categories; regular services leave this empty
     * and keep the category→position filter on App\Models\Position.
     */
    public function positions()
    {
        return $this->belongsToMany(Position::class, 'service_positions')->withTimestamps();
    }
}
