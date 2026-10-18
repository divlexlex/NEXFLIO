<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One required staff role on a Packages appointment — see the
 * appointment_personnel migration. Regular (non-package) bookings never
 * create rows here.
 */
class AppointmentPersonnel extends Model
{
    protected $table = 'appointment_personnel';

    protected $fillable = [
        'appointment_id',
        'position_id',
        'user_id',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
