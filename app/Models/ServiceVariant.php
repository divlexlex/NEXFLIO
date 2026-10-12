<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A saleable tier of a Service (e.g. Solo / Couple / Barkada for the
 * head-spa packages). When a service has variants the branch booking flow
 * requires one and charges its price.
 */
class ServiceVariant extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'service_id',
        'label',
        'price',
        'guests',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'guests' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * "Solo (1 guest)" style hint for the booking selector; Barkada has no
     * fixed guest count.
     */
    public function guestLabel(): ?string
    {
        if ($this->guests === null) {
            return null;
        }

        return $this->guests === 1 ? '1 guest' : $this->guests.' guests';
    }
}
