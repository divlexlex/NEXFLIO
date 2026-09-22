<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffSchedule extends Model
{
    protected $fillable = ['user_id', 'day_of_week', 'start_time', 'end_time', 'is_available'];

    protected function casts(): array
    {
        return [
            'day_of_week'  => 'integer',
            'start_time'   => 'datetime:H:i:s',
            'end_time'     => 'datetime:H:i:s',
            'is_available' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
