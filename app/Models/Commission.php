<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable earnings ledger — priced at completion time. Regular bookings
 * write one row; Packages write one row per included service / assigned
 * tech. Never edited; the unique (appointment, user, included_service)
 * constraint makes creation idempotent.
 */
class Commission extends Model
{
    protected $fillable = [
        'user_id',
        'appointment_id',
        'included_service_id',
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

    /** The included catalog service whose original price drove this row (packages). */
    public function includedService()
    {
        return $this->belongsTo(Service::class, 'included_service_id');
    }
}
