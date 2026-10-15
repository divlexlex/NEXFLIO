<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'appointment_id',
        'user_id',
        'service_rating',
        'personnel_rating',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'service_rating' => 'integer',
            'personnel_rating' => 'integer',
        ];
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
