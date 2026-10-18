<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class BusinessHours extends Model
{
    /**
     * Per-day-type default open/close times ('H:i:s'). Weekdays (Mon–Fri)
     * open at noon; weekends (Sat–Sun) open in the morning. Both close at
     * 10:00 PM same calendar day — no overnight / post-midnight starts on
     * that day's schedule (see windowForDay() / slotStartAllowed()).
     */
    public const DEFAULT_WEEKDAY_OPEN = '12:00:00';
    public const DEFAULT_WEEKDAY_CLOSE = '22:00:00';
    public const DEFAULT_WEEKEND_OPEN = '10:00:00';
    public const DEFAULT_WEEKEND_CLOSE = '22:00:00';

    protected $fillable = ['day_of_week', 'open_at', 'close_at', 'is_open'];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'open_at'     => 'datetime:H:i:s',
            'close_at'    => 'datetime:H:i:s',
            'is_open'     => 'boolean',
        ];
    }

    public static function dayName(int $day): string
    {
        return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$day];
    }

    public static function hoursForDay(int $day): ?self
    {
        return static::where('day_of_week', $day)->first();
    }

    /** Saturday (6) and Sunday (0) use the weekend defaults. */
    public static function isWeekend(int $day): bool
    {
        return $day === 0 || $day === 6;
    }

    /**
     * The default open/close pair for a day, as ['open_at' => 'H:i:s',
     * 'close_at' => 'H:i:s']. Used for fresh seeds and for the stored
     * fallback times of a Closed day (the columns are NOT NULL).
     */
    public static function defaultsForDay(int $day): array
    {
        return static::isWeekend($day)
            ? ['open_at' => self::DEFAULT_WEEKEND_OPEN, 'close_at' => self::DEFAULT_WEEKEND_CLOSE]
            : ['open_at' => self::DEFAULT_WEEKDAY_OPEN, 'close_at' => self::DEFAULT_WEEKDAY_CLOSE];
    }

    /**
     * The booking window for a day as [openMinute, effectiveCloseMinute],
     * both minutes from midnight, or null when the day is closed. A close at
     * or before open means the window runs past midnight, so the effective
     * close is pushed into the next day (e.g. 12:00–01:00 → 720–1500).
     */
    public static function windowForDay(int $day): ?array
    {
        $hours = static::where('day_of_week', $day)->first();

        if (! $hours || ! $hours->is_open) {
            return null;
        }

        $openMinute  = (int) Carbon::parse($hours->open_at)->hour * 60 + (int) Carbon::parse($hours->open_at)->minute;
        $closeMinute = (int) Carbon::parse($hours->close_at)->hour * 60 + (int) Carbon::parse($hours->close_at)->minute;

        $effectiveClose = $closeMinute <= $openMinute ? $closeMinute + 1440 : $closeMinute;

        return [$openMinute, $effectiveClose];
    }

    /**
     * The ONE shared slot rule: a start time is offered only when it is at
     * or after opening, before midnight, and the service finishes by the
     * (overnight-aware) close. With the default 10:00 PM close, a 60-minute
     * service last starts at 9:00 PM — nothing past closing that day.
     */
    public static function slotStartAllowed(int $startMinute, int $duration, int $openMinute, int $effectiveClose): bool
    {
        return $startMinute >= $openMinute
            && $startMinute < 1440
            && ($startMinute + $duration) <= $effectiveClose;
    }

    /**
     * True when $startTime (H:i) on $date lets a $durationMinutes service
     * finish by that day's closing time. Used to reject forged bookings
     * past shop hours (e.g. same-day past 10:00 PM → book another day).
     */
    public static function startFitsOnDate(string $date, string $startTime, int $durationMinutes): bool
    {
        $window = static::windowForDay(Carbon::parse($date)->dayOfWeek);
        if ($window === null) {
            return false;
        }

        [$openMinute, $effectiveClose] = $window;
        $parts = explode(':', substr($startTime, 0, 5));
        $startMinute = ((int) $parts[0]) * 60 + ((int) ($parts[1] ?? 0));

        return static::slotStartAllowed($startMinute, $durationMinutes, $openMinute, $effectiveClose);
    }

    /**
     * Human-readable public hours lines, grouped over consecutive days that
     * share the same open/close (reading Monday→Sunday). Weekday and weekend
     * therefore print as separate lines when they differ — e.g.
     * "Monday – Friday: 12:00 PM – 10:00 PM", "Saturday – Sunday: 10:00 AM – 10:00 PM".
     *
     * @return list<string>
     */
    public static function publicLines(): array
    {
        $byDay = static::all()->keyBy('day_of_week');

        $rank  = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'];
        $order = array_keys($rank);

        $entries = [];
        foreach ($order as $day) {
            $row = $byDay->get($day);

            if (! $row || ! $row->is_open) {
                $entries[] = ['day' => $day, 'key' => 'closed', 'text' => 'Closed'];

                continue;
            }

            $open  = Carbon::parse($row->open_at)->format('g:i A');
            $close = Carbon::parse($row->close_at)->format('g:i A');

            $entries[] = ['day' => $day, 'key' => $open.'|'.$close, 'text' => $open.' – '.$close];
        }

        $lines = [];
        $count = count($entries);
        for ($i = 0; $i < $count; $i++) {
            $j = $i;
            while ($j + 1 < $count && $entries[$j + 1]['key'] === $entries[$i]['key']) {
                $j++;
            }

            $from  = $rank[$entries[$i]['day']];
            $to    = $rank[$entries[$j]['day']];
            $label = $i === $j ? $from : $from.' – '.$to;

            $lines[] = $label.': '.$entries[$i]['text'];

            $i = $j;
        }

        return $lines;
    }
}
