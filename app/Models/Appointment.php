<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Appointment extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'reference_code',
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

    /**
     * The booking-time (primary) payment row — always the FIRST payment on
     * the appointment (oldestOfMany), never a later settlement, partial
     * receipt, or top-up. Every "primary payment" read in the codebase goes
     * through here.
     */
    public function payment()
    {
        return $this->hasOne(Payment::class)->oldestOfMany();
    }

    /**
     * Every payment row on this booking — the first is the booking-time
     * payment (online reservation fee / full online amount / cash promise),
     * later rows are branch settlements, partial receipts, or top-ups.
     * payment() (hasOne) always resolves to that first row.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /** The client's 1–5 star rating of this visit, if they left one. */
    public function review()
    {
        return $this->hasOne(Review::class);
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

    /** Verified money collected online (fees, partial receipts, top-ups) — everything not taken at the branch. */
    public function paidOnline(): float
    {
        $payments = $this->relationLoaded('payments')
            ? $this->payments
            : $this->payments()->get();

        return (float) $payments
            ->where('status', PaymentStatus::Verified)
            ->where('kind', '!=', 'branch')
            ->sum('amount');
    }

    /** Verified money taken at the branch — kind='branch' rows stamped by the branch-payment endpoint only. */
    public function paidAtBranch(): float
    {
        $payments = $this->relationLoaded('payments')
            ? $this->payments
            : $this->payments()->get();

        return (float) $payments
            ->where('status', PaymentStatus::Verified)
            ->where('kind', 'branch')
            ->sum('amount');
    }

    /** What's still owed at the branch: booked total minus verified money, never negative. */
    public function balanceDue(): float
    {
        $total = (float) ($this->booked_price ?? $this->service?->price ?? 0);

        return max(0, round($total - $this->verifiedPaid(), 2));
    }

    /**
     * Money the branch actually holds from topup_eligible rejections —
     * verified partial_receipt rows only. The rejected primary row itself
     * never counts (it was never collected).
     */
    public function partialReceiptTotal(): float
    {
        $payments = $this->relationLoaded('payments')
            ? $this->payments
            : $this->payments()->get();

        return (float) $payments
            ->where('kind', 'partial_receipt')
            ->where('status', PaymentStatus::Verified)
            ->sum('amount');
    }

    /**
     * What the client still has to add to complete the original charge:
     * primary payment amount minus recorded partial receipts, never
     * negative. Drives the "Continue to book this" amount and the top-up
     * charge — always recomputed, never stored.
     */
    public function shortfall(): float
    {
        $primary = $this->payment;

        if (! $primary) {
            return 0;
        }

        return max(0, round((float) $primary->amount - $this->partialReceiptTotal(), 2));
    }

    /** A top-up that still matters: kind topup, Pending or already Verified. */
    public function hasActiveTopup(): bool
    {
        $payments = $this->relationLoaded('payments')
            ? $this->payments
            : $this->payments()->get();

        return $payments
            ->where('kind', 'topup')
            ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Verified])
            ->isNotEmpty();
    }

    /**
     * Whether "Continue to book this" may be offered: rejected primary with
     * a topup_eligible category, no refund in motion, no live top-up, money
     * still missing, and the slot still upcoming. Every condition is
     * re-checked server-side on the top-up form and on submit.
     */
    public function canContinueBooking(): bool
    {
        $primary = $this->payment;

        if (! $primary || $primary->status !== PaymentStatus::Rejected) {
            return false;
        }

        $flags = $primary->rejection_category
            ? config('payment.rejection_reasons.'.$primary->rejection_category, [])
            : [];

        if (empty($flags['topup_eligible'])) {
            return false;
        }

        if ($primary->refund_status !== null) {
            return false;
        }

        if ($this->hasActiveTopup()) {
            return false;
        }

        if ($this->shortfall() <= 0) {
            return false;
        }

        try {
            $start = Carbon::parse(
                $this->appointment_date->toDateString().' '.substr((string) $this->start_time, 0, 5)
            );
        } catch (\Throwable) {
            return false;
        }

        return $start->isFuture();
    }

    /**
     * Whether "Send a new proof" may be offered: rejected primary with a
     * resubmit_proof category, appointment still Cancelled post-rejection,
     * and the slot still upcoming. The live slot-occupancy re-check happens
     * server-side on submit (shared with the top-up flow). Mirrors
     * canContinueBooking() for the resubmit path.
     */
    public function canResubmitProof(): bool
    {
        $primary = $this->payment;

        if (! $primary || $primary->status !== PaymentStatus::Rejected) {
            return false;
        }

        $flags = $primary->rejection_category
            ? config('payment.rejection_reasons.'.$primary->rejection_category, [])
            : [];

        if (empty($flags['resubmit_proof'])) {
            return false;
        }

        if ($this->status !== AppointmentStatus::Cancelled) {
            return false;
        }

        try {
            $start = Carbon::parse(
                $this->appointment_date->toDateString().' '.substr((string) $this->start_time, 0, 5)
            );
        } catch (\Throwable) {
            return false;
        }

        return $start->isFuture();
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

        // A rejected booking-time payment on a dead booking is its own state —
        // without this it falls through to "Awaiting payment verification",
        // which wrongly reads as actionable on cancelled bookings in the
        // client History. Live bookings (e.g. Booked via a verified top-up)
        // skip this and derive off the held money below instead.
        if ($this->payment?->status === PaymentStatus::Rejected
            && in_array($this->status, [AppointmentStatus::Cancelled, AppointmentStatus::NoShow], true)) {
            return 'Payment rejected';
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
            return $this->status === AppointmentStatus::Unverified
                ? 'Awaiting confirmation — full amount payable at the spa'
                : 'Cash — pay at the branch';
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
     * At-a-glance refund remark for appointments cancelled through an
     * approved cancellation change request: 'refund' for a stored
     * refundable decision (or, on legacy decision-less approvals, while a
     * refund is in flight or completed), 'no_refund' for a stored
     * non-refundable decision (or legacy approvals with money paid but no
     * refund moving), null otherwise (no approved cancellation request, or
     * nothing was ever paid). A stored manager decision always wins; rows
     * approved before the decision column existed keep the payments'
     * refund_status behaviour.
     */
    public function cancellationRefundRemark(): ?string
    {
        $requests = $this->relationLoaded('changeRequests')
            ? $this->changeRequests
            : $this->changeRequests()->get();

        $approved = $requests
            ->where('type', AppointmentChangeRequest::TYPE_CANCELLATION)
            ->where('status', AppointmentChangeRequest::STATUS_APPROVED)
            ->sortByDesc('id')
            ->first();

        if (! $approved || $this->verifiedPaid() <= 0) {
            return null;
        }

        if ($approved->refund_decision === 'refundable') {
            return 'refund';
        }

        if ($approved->refund_decision === 'non_refundable') {
            return 'no_refund';
        }

        $payments = $this->relationLoaded('payments')
            ? $this->payments
            : $this->payments()->get();

        return $payments->contains(
            fn ($payment) => in_array($payment->refund_status, ['requested', 'sent'], true)
        ) ? 'refund' : 'no_refund';
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
     * Client-facing Appointment ID, e.g. PN-20261015-000123: PN, the
     * creation date, and the appointment id 6-padded. Persisted to
     * reference_code at creation (all three creation paths save it) so
     * feeds and listings read it straight off the row; computed on the fly
     * as the fallback for rows that predate the column.
     */
    public function referenceCode(): string
    {
        if ($this->reference_code) {
            return $this->reference_code;
        }

        $date = ($this->created_at ?? now())->format('Ymd');

        return sprintf('PN-%s-%06d', $date, (int) $this->id);
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

    /**
     * The appointment's start time as a 12-hour display label, e.g. "2:00 PM".
     * Display only — the raw H:i(:s) column value is untouched for storage,
     * slot math, and payloads.
     */
    public function startTimeLabel(): string
    {
        return Carbon::parse((string) $this->start_time)->format('g:i A');
    }
}
