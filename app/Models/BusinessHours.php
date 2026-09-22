<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessHours extends Model
{
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
}
