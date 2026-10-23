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
        'start_time',
        'added_by',
        'source',
    ];

    /** Chosen in the Walk-in Entry form at creation (an "availed" service). */
    public function isCreation(): bool
    {
        return $this->source === 'creation';
    }

    /** Picked as an extra inside the client's online booking wizard. */
    public function isBooking(): bool
    {
        return $this->source === 'booking';
    }

    /**
     * Added later from the Payments add flow. Only these carry the "Added"
     * tag and a down-payment sub-line: creation and booking extras were part
     * of the original avail, and their DP (if any) was collected with the
     * booking payment.
     */
    public function isPayment(): bool
    {
        return $this->source === 'payment';
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'start_time' => 'datetime:H:i',
        ];
    }

    /** Its own start time when the online wizard gave it one, else null. */
    public function startTimeLabel(): ?string
    {
        return $this->start_time?->format('g:i A');
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
