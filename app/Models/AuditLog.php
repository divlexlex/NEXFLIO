<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable forensic log. Rows are created by the Auditable trait and can
 * never be updated or deleted — enforced here, not just by convention.
 */
class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Audit logs are immutable.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Audit logs are immutable.');
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }
}
