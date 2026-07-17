<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable earnings ledger — one row per completed appointment, priced at
 * completion time. Never edited; the unique appointment_id constraint makes
 * creation idempotent.
 */
class Commission extends Model
{
    protected $fillable = [
        'user_id',
        'appointment_id',
        'service_price',
        'rate',
        'amount',
        'earned_at',
    ];

    protected function casts(): array
    {
        return [
            'service_price' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'earned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Commission records are immutable.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Commission records are immutable.');
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
