<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'walk_in_name',
        'walk_in_phone',
        'service_id',
        'variant_label',
        'booked_price',
        'personnel_id',
        'appointment_date',
        'start_time',
        'status',
        'notes',
        'payment_proof_path',
    ];

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'appointment_date' => 'datetime',
            'booked_price' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function personnel()
    {
        return $this->belongsTo(User::class, 'personnel_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Home Service address snapshot (Phase 3B) — absent for Branch Booking
     * appointments. See App\Models\AppointmentAddress for why this is a
     * copy, not a live reference to client_addresses.
     */
    public function address()
    {
        return $this->hasOne(AppointmentAddress::class);
    }

    public function changeRequests()
    {
        return $this->hasMany(AppointmentChangeRequest::class);
    }

    /**
     * At most one open (pending) client change request at a time — enforced
     * in StoreAppointmentChangeRequest, surfaced on the client's bookings
     * page and the Manager review queue.
     */
    public function pendingChangeRequest()
    {
        return $this->hasOne(AppointmentChangeRequest::class)
            ->where('status', AppointmentChangeRequest::STATUS_PENDING)
            ->latestOfMany();
    }

    /**
     * Registered clients book under their account; walk-ins only have a name
     * the manager typed in.
     */
    public function clientName(): ?string
    {
        return $this->user?->name ?? $this->walk_in_name;
    }

    /**
     * Proof uploads now live on the payment row; the old column sticks around
     * until the mobile app reads payment.proof_path directly.
     */
    public function getPaymentProofPathAttribute(?string $value): ?string
    {
        return $value ?? $this->payment?->proof_path;
    }

    /**
     * What the client was charged. Snapshot on the row for package tiers;
     * rows booked before variants (or regular services) fall back to the
     * service's current price.
     */
    public function getPriceAttribute(): ?string
    {
        return $this->booked_price ?? $this->service?->price;
    }

    /**
     * Service name with the package tier when one was booked, e.g.
     * "Package LIZ (Couple)". Plain name for regular services.
     */
    public function displayServiceName(): string
    {
        $name = $this->service?->name ?? 'Service';

        return $this->variant_label ? $name.' ('.$this->variant_label.')' : $name;
    }
}
