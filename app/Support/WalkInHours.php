<?php

namespace App\Support;

use App\Models\BusinessHours;
use Illuminate\Support\Carbon;

/**
 * Walk-in Entry closing rules — counter bookings only.
 *
 * Online / API / branch / home stores keep using BusinessHours::startFitsOnDate().
 * Walk-ins may finish as late as config('booking.walk_in.close_at') (default
 * 01:00 the next calendar morning), including starts that cross midnight.
 */
class WalkInHours
{
    /** Configured walk-in finish cutoff as H:i (e.g. "01:00"). */
    public static function closeAt(): string
    {
        $raw = (string) config('booking.walk_in.close_at', '01:00');

        return substr($raw, 0, 5);
    }

    /** Configured walk-in finish cutoff as minutes from midnight. */
    public static function closeMinute(): int
    {
        return static::minutesOf(static::closeAt());
    }

    /**
     * True when $startTime (H:i) on $date lets a $durationMinutes walk-in
     * finish by the configured walk-in close. Early-morning clock times
     * before that close are treated as the overnight extension of the
     * appointment date (00:00 → 24:00 when close is 01:00).
     */
    public static function startFitsOnDate(string $date, string $startTime, int $durationMinutes): bool
    {
        $bounds = static::windowForDate($date);
        if ($bounds === null) {
            return false;
        }

        [$openMinute, $effectiveClose, $closeMinute] = $bounds;
        $startMinute = static::resolveStartMinute($startTime, $closeMinute, $effectiveClose);

        return $startMinute >= $openMinute
            && ($startMinute + max(0, $durationMinutes)) <= $effectiveClose;
    }

    /**
     * Minutes-from-midnight start used for walk-in capacity / conflict checks
     * (may exceed 1440 for post-midnight starts on the appointment date).
     */
    public static function startMinuteOnDate(string $date, string $startTime): ?int
    {
        $bounds = static::windowForDate($date);
        if ($bounds === null) {
            return null;
        }

        [, $effectiveClose, $closeMinute] = $bounds;

        return static::resolveStartMinute($startTime, $closeMinute, $effectiveClose);
    }

    /**
     * Open + walk-in effective close for $date, or null when the shop is closed.
     *
     * @return array{0: int, 1: int, 2: int}|null [openMinute, effectiveClose, closeMinute]
     */
    public static function windowForDate(string $date): ?array
    {
        $hours = BusinessHours::hoursForDay(Carbon::parse($date)->dayOfWeek);
        if (! $hours || ! $hours->is_open) {
            return null;
        }

        $openMinute = static::minutesOf(Carbon::parse($hours->open_at)->format('H:i'));
        $closeMinute = static::closeMinute();
        $effectiveClose = $closeMinute <= $openMinute
            ? $closeMinute + 1440
            : $closeMinute;

        return [$openMinute, $effectiveClose, $closeMinute];
    }

    /**
     * Map an H:i clock time onto the appointment-date timeline. When the
     * walk-in window runs past midnight, times strictly before the close
     * clock (e.g. 00:00–00:59 for a 01:00 close) belong to the overnight
     * extension (+1440).
     */
    public static function resolveStartMinute(string $startTime, int $closeMinute, int $effectiveClose): int
    {
        $clockMinute = static::minutesOf($startTime);

        if ($effectiveClose > 1440 && $clockMinute < $closeMinute) {
            return $clockMinute + 1440;
        }

        return $clockMinute;
    }

    private static function minutesOf(string $hhmm): int
    {
        $parts = explode(':', substr($hhmm, 0, 5));

        return ((int) $parts[0]) * 60 + ((int) ($parts[1] ?? 0));
    }
}
