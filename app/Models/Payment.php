<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'appointment_id',
        'kind',
        'amount',
        'amount_received',
        'method',
        'proof_path',
        'status',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'rejection_category',
        'refund_status',
        'refund_requested_at',
        'refund_sent_at',
        'refund_sent_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_received' => 'decimal:2',
            'status' => PaymentStatus::class,
            'verified_at' => 'datetime',
            'refund_requested_at' => 'datetime',
            'refund_sent_at' => 'datetime',
        ];
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Display label for the structured rejection category (see
     * config/payment.php 'rejection_reasons'). Null when the payment was
     * never rejected or carries no category (pre-category rejections, API
     * cancels). A stored key missing from config (retired categories like
     * duplicate_booking) falls back to a prettified key so historical rows
     * still render.
     */
    public function rejectionCategoryLabel(): ?string
    {
        if (! $this->rejection_category) {
            return null;
        }

        return config(
            'payment.rejection_reasons.'.$this->rejection_category.'.label',
            ucfirst(str_replace('_', ' ', $this->rejection_category))
        );
    }

    /**
     * Admin badge text for step-2B rows. Null for ordinary booking-time
     * payments, which need no badge.
     */
    public function kindLabel(): ?string
    {
        return match ($this->kind) {
            'topup' => 'Top-up',
            'partial_receipt' => 'Partial receipt',
            default => null,
        };
    }

    /**
     * True refund amount for every refund display (admin card + client
     * lines + mail): the rejection flow stores what was actually received
     * in amount_received; the cancellation-approval flow leaves it null
     * and the money fact is the appointment's verifiedPaid() total at
     * approval time. One helper, used by both flows, so both print the
     * true amount.
     */
    public function refundDueAmount(): float
    {
        if ($this->amount_received !== null) {
            return (float) $this->amount_received;
        }

        return (float) ($this->appointment?->verifiedPaid() ?? 0);
    }
}
