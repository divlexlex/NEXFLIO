<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One owner-entered correction to a staff/manager leave balance.
 *
 * `days` is signed: negative reduces the remaining balance, positive adds
 * back. Rows are never overwritten by the balance calculation — they are
 * summed per (user, type, year) on top of the computed approved-days figure,
 * so a later approval cannot erase the correction. See App\Support\LeaveBalances.
 */
class LeaveAdjustment extends Model
{
    public const TYPE_SICK = 'sick';
    public const TYPE_VACATION = 'vacation';

    public const TYPES = [self::TYPE_SICK, self::TYPE_VACATION];

    protected $fillable = [
        'user_id',
        'type',
        'days',
        'reason',
        'created_by',
        'year',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'integer',
            'year' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
