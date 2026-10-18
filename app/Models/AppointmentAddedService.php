<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A service added to an already-booked appointment under the same
 * Appointment ID. The price is snapshotted at add time and the appointment's
 * booked_price is raised by it, so balances, check-out, and revenue follow
 * without touching the original service line. Immutable once the visit is
 * completed — additions are only offered while the appointment is Booked or
 * In-Service (enforced by the controller).
 */
class AppointmentAddedService extends Model
{
    protected $fillable = [
        'appointment_id',
        'service_id',
        'variant_label',
        'price',
        'personnel_id',
        'added_by',
        'source',
    ];

    /** Chosen in the Walk-in Entry form at creation (an "availed" service). */
    public function isCreation(): bool
    {
        return $this->source === 'creation';
    }

    /** Added later from the Payments add flow. */
    public function isPayment(): bool
    {
        return $this->source !== 'creation';
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /** The staff member who performs this added service (may be null). */
    public function personnel()
    {
        return $this->belongsTo(User::class, 'personnel_id');
    }

    /** The manager who added it. */
    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /** "Service name — Variant" for receipts and cards. */
    public function displayName(): string
    {
        $name = $this->service->name ?? 'Service';

        return $this->variant_label ? $name.' — '.$this->variant_label : $name;
    }
}
