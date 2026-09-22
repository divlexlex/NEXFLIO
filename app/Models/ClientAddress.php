<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientAddress extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'user_id',
        'label',
        'street_address',
        'barangay',
        'city_municipality',
        'province',
        'postal_code',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * "Street, Barangay, City/Municipality, Province PostalCode" — the one
     * display line every future view (Profile, and eventually the Phase 3B
     * Home Service address step) should use instead of re-assembling the
     * parts inline.
     */
    public function formatted(): string
    {
        $line = "{$this->street_address}, {$this->barangay}, {$this->city_municipality}, {$this->province}";

        return $this->postal_code ? "{$line} {$this->postal_code}" : $line;
    }
}
