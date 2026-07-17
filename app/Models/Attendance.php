<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use Auditable;

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
}
