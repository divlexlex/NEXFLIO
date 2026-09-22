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
        'name', 'email', 'password', 'role_id', 'email_verified_at',
        'first_name', 'last_name', 'middle_name', 'contact_number', 'username',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

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

    /**
     * Client-only structured profile (name parts, gender, birthdate,
     * mobile) — 0..1, optional even for role_id=4 accounts that haven't
     * completed it yet. See App\Models\ClientProfile.
     */
    public function clientProfile()
    {
        return $this->hasOne(ClientProfile::class);
    }

    /**
     * Client-only saved addresses — 0..n, independent of clientProfile()
     * existing (see the schema proposal's reasoning for keying off user_id
     * directly rather than chaining through client_profiles).
     */
    public function addresses()
    {
        return $this->hasMany(ClientAddress::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function staffSchedules()
    {
        return $this->hasMany(StaffSchedule::class);
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }

    public function verificationCodes()
    {
        return $this->hasMany(VerificationCode::class);
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

    public function fullName(): string
    {
        $parts = array_filter([$this->first_name, $this->middle_name, $this->last_name]);
        return $parts ? implode(' ', $parts) : $this->name;
    }
}
