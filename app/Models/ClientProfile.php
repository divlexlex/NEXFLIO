<?php

namespace App\Models;

use App\Enums\Gender;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientProfile extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'birthdate',
        'mobile_number',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birthdate' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * "First Middle Last", middle segment omitted when blank — the same
     * value users.name is kept synchronized to server-side. Exposed here
     * too so profile-specific views/reports can read it without depending
     * on users.name staying in sync (it always will, but this keeps the
     * profile model self-contained).
     */
    public function fullName(): string
    {
        return trim(preg_replace('/\s+/', ' ', "{$this->first_name} {$this->middle_name} {$this->last_name}"));
    }
}
