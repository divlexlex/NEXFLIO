<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * A Client's request to change one of their own appointments — either a
 * cancellation or a reschedule proposal. Nothing on the appointment itself
 * moves until a Manager approves (see App\Http\Controllers\Web\AppointmentRequestController).
 * Approval routes through App\Services\AppointmentService for cancellations
 * so payment/commission side effects stay consistent with every other path.
 */
class AppointmentChangeRequest extends Model
{
    use Auditable;

    public const TYPE_CANCELLATION = 'cancellation';

    public const TYPE_RESCHEDULE = 'reschedule';

    public const TYPES = [self::TYPE_CANCELLATION, self::TYPE_RESCHEDULE];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'appointment_id',
        'requested_by',
        'type',
        'status',
        'reason',
        'requested_date',
        'requested_start_time',
        'requested_personnel_id',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function requestedPersonnel()
    {
        return $this->belongsTo(User::class, 'requested_personnel_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isReschedule(): bool
    {
        return $this->type === self::TYPE_RESCHEDULE;
    }
}
