<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedSlot extends Model
{
    protected $fillable = ['blocked_date', 'start_time', 'end_time', 'personnel_id', 'reason', 'created_by'];

    protected function casts(): array
    {
        return [
            'blocked_date' => 'date',
            'start_time'   => 'datetime:H:i:s',
            'end_time'     => 'datetime:H:i:s',
        ];
    }

    public function personnel()
    {
        return $this->belongsTo(User::class, 'personnel_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
