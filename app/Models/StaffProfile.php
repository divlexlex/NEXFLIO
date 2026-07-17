<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffProfile extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'user_id',
        'base_pay',
        'commission_rate',
        'position',
        'is_on_break',
        'break_started_at',
        'employment_status',
        'hired_at',
    ];

    protected function casts(): array
    {
        return [
            'base_pay' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'is_on_break' => 'boolean',
            'break_started_at' => 'datetime',
            'hired_at' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
