<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use Auditable, HasApiTokens, Notifiable, SoftDeletes;

    public const ROLE_SUPER_ADMIN = 1;
    public const ROLE_MANAGER = 2;
    public const ROLE_STAFF = 3;
    public const ROLE_CLIENT = 4;

    protected $fillable = [
        'name', 'email', 'password', 'role_id',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function assignedAppointments()
    {
        return $this->hasMany(Appointment::class, 'personnel_id');
    }

    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }

    public function isClient(): bool
    {
        return $this->role_id === self::ROLE_CLIENT;
    }

    public function isStaff(): bool
    {
        return $this->role_id === self::ROLE_STAFF;
    }

    public function isManagerOrAbove(): bool
    {
        return in_array($this->role_id, [self::ROLE_SUPER_ADMIN, self::ROLE_MANAGER], true);
    }
}
