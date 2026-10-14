<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One staff member's shift for one roster week (AM/PM with that shift's
 * times). At most one row per person per week (unique user_id/week_start);
 * a person works either AM or PM in a given week, never both. Weeks are
 * keyed by their Sunday (week_start).
 */
class StaffShiftAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'week_start',
        'shift',
        'start_time',
        'end_time',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForWeek($query, string $weekStart)
    {
        return $query->where('week_start', $weekStart);
    }

    /**
     * The Sunday (Y-m-d) of the week containing the given date, computed
     * arithmetically: Sunday is dayOfWeek 0, so the week start is the date
     * minus its dayOfWeek in days.
     */
    public static function weekStartOf(string $date): string
    {
        $day = Carbon::parse($date);

        return $day->copy()->subDays($day->dayOfWeek)->toDateString();
    }

    /** The person's assignment for the week containing the given date, if any. */
    public static function assignmentFor(int $userId, string $date): ?self
    {
        return static::where('user_id', $userId)
            ->forWeek(static::weekStartOf($date))
            ->first();
    }

    /** Whether the week starting on the given Sunday has any roster at all. */
    public static function weekHasRoster(string $weekStart): bool
    {
        return static::forWeek($weekStart)->exists();
    }
}
