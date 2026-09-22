<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Home Service appointment-address snapshot — see the migration's doc
 * comment for why these columns are copied at booking time instead of
 * living only as a foreign key to client_addresses.
 */
class AppointmentAddress extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'appointment_id',
        'source_client_address_id',
        'street_address',
        'barangay',
        'city_municipality',
        'province',
        'postal_code',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function sourceClientAddress()
    {
        return $this->belongsTo(ClientAddress::class, 'source_client_address_id');
    }

    public function formatted(): string
    {
        $line = "{$this->street_address}, {$this->barangay}, {$this->city_municipality}, {$this->province}";

        return $this->postal_code ? "{$line} {$this->postal_code}" : $line;
    }
}
