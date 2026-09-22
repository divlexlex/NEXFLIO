<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ephemeral security artifact (short-lived, hashed, single-use) — not a
 * business record, so unlike most models in this app it deliberately skips
 * Auditable/SoftDeletes, consistent with the framework's own
 * password_reset_tokens table.
 */
class VerificationCode extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'channel',
        'attempts',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
