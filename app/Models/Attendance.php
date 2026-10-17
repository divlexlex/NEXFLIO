<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Attendance extends Model
{
    use Auditable;

    /** Minutes of grace after the scheduled start before a shift counts as late. */
    public const LATE_GRACE_MINUTES = 10;

    protected $fillable = [
        'user_id',
        'work_date',
        'time_in',
        'time_out',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'time_in' => 'datetime',
            'time_out' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whole minutes worked. Null before a time-in; an open shift (no
     * time-out) counts up to now. Never negative.
     */
    public function workedMinutes(): ?int
    {
        if ($this->time_in === null) {
            return null;
        }

        $end = $this->time_out ?? now();
        $seconds = $end->getTimestamp() - $this->time_in->getTimestamp();

        return max(0, intdiv($seconds, 60));
    }

    /**
     * Human duration for the worked time, e.g. "7h 30m", "45m", "8h 00m".
     * Null when there is no time-in.
     */
    public function workedForHumans(): ?string
    {
        $minutes = $this->workedMinutes();

        if ($minutes === null) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $mins  = $minutes % 60;

        return $hours > 0
            ? sprintf('%dh %02dm', $hours, $mins)
            : sprintf('%dm', $mins);
    }

    /**
     * The shift start this attendance is measured against: the roster
     * assignment for the date when one exists, otherwise the staff member's
     * standing schedule for that weekday. Null when neither is known.
     */
    public function scheduledStart(): ?Carbon
    {
        if ($this->work_date === null) {
            return null;
        }

        $date = $this->work_date->toDateString();

        $assignment = StaffShiftAssignment::assignmentFor($this->user_id, $date);
        if ($assignment && $assignment->start_time) {
            return Carbon::parse($date.' '.$assignment->start_time);
        }

        $schedule = StaffSchedule::where('user_id', $this->user_id)
            ->where('day_of_week', $this->work_date->dayOfWeek)
            ->where('is_available', true)
            ->first();

        if ($schedule && $schedule->start_time) {
            return Carbon::parse($date.' '.$schedule->start_time->format('H:i:s'));
        }

        return null;
    }

    /**
     * Minutes past the scheduled start (plus grace). Null when there is no
     * schedule to compare against or no time-in; never negative.
     */
    public function lateMinutes(): ?int
    {
        $scheduled = $this->scheduledStart();

        if ($scheduled === null || $this->time_in === null) {
            return null;
        }

        $cutoff  = $scheduled->copy()->addMinutes(self::LATE_GRACE_MINUTES);
        $seconds = $this->time_in->getTimestamp() - $cutoff->getTimestamp();

        return max(0, intdiv($seconds, 60));
    }

    public function isLate(): bool
    {
        return ($this->lateMinutes() ?? 0) > 0;
    }
}
