<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
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
        'guests',
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
     * Every payment row on this booking — the first is the booking-time
     * payment (online reservation fee / full online amount / cash promise),
     * later rows are branch settlements recorded at the spa. payment()
     * (hasOne) is kept for the many single-payment reads and always resolves
     * to that first row.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /** Sum of verified payment amounts — the only money that counts. */
    public function verifiedPaid(): float
    {
        // Reuse the eager-loaded collection when the caller already loaded
        // `payments` (admin list, My Bookings) instead of re-querying per row.
        $payments = $this->relationLoaded('payments')
            ? $this->payments
            : $this->payments()->get();

        return (float) $payments
            ->where('status', PaymentStatus::Verified)
            ->sum('amount');
    }

    /** What's still owed at the branch: booked total minus verified money, never negative. */
    public function balanceDue(): float
    {
        $total = (float) ($this->booked_price ?? $this->service?->price ?? 0);

        return max(0, round($total - $this->verifiedPaid(), 2));
    }

    /**
     * Client/manager-facing payment state, derived from the booked total plus
     * verified payments only — pending proofs and cash promises never count
     * as paid.
     */
    public function paymentState(): string
    {
        if ($this->balanceDue() <= 0) {
            return 'Paid in full';
        }

        if ($this->verifiedPaid() > 0) {
            return 'Down payment paid — balance ₱'.number_format($this->balanceDue(), 2);
        }

        // The payments() relation is id-ordered; reorder() so "latest" really
        // means latest instead of appending a second ORDER BY clause.
        $method = ($this->relationLoaded('payments') ? $this->payments->sortByDesc('id')->first() : null)
            ?? $this->payments()->reorder()->latest('id')->first();
        $method = $method?->method ?? $this->payment?->method;

        if ($method === 'cash') {
            return 'Cash — pay at the branch';
        }

        return 'Awaiting payment verification';
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
