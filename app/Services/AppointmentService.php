<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Mail\PaymentRejectedMail;
use App\Mail\PaymentVerifiedMail;
use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\Commission;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single path for every appointment status change, no matter which endpoint
 * triggered it — so payment verification, commission accrual, and inventory
 * consumption can never be skipped.
 */
class AppointmentService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * @param array{
     *     rejection_category?: string|null,
     *     rejection_reason?: string|null,
     *     amount_received?: float|int|string|null,
     *     payment_id?: int|null,
     *     items?: array<int, array{inventory_id: int, quantity: int}>,
     *     notify_body?: string|null,
     * } $options
     *
     * @throws AuthorizationException on role violations (renders 403)
     * @throws ValidationException on invalid transitions/input (renders 422)
     */
    public function transition(
        Appointment $appointment,
        AppointmentStatus $target,
        User $actor,
        array $options = [],
    ): Appointment {
        $current = $appointment->status;

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Cannot change status from '{$current->value}' to '{$target->value}'.",
            ]);
        }

        // A service in progress can't be started while the client asks to
        // cancel it: a pending cancellation request blocks the move to
        // In-Service until it is approved, rejected, or withdrawn. The
        // approval and Payments-rejection paths target Cancelled, so they
        // are unaffected by this guard.
        if ($target === AppointmentStatus::InService && AppointmentChangeRequest::where('appointment_id', $appointment->id)
            ->where('type', AppointmentChangeRequest::TYPE_CANCELLATION)
            ->where('status', AppointmentChangeRequest::STATUS_PENDING)
            ->exists()) {
            throw ValidationException::withMessages([
                'status' => 'This appointment has a pending cancellation request — review it in Change Requests first.',
            ]);
        }

        // Verifying or rejecting payment is a manager decision; everything
        // after that is open to the assigned staff member as well.
        $verificationStep = $current === AppointmentStatus::Unverified;
        $isAssignedStaff = $actor->id === $appointment->personnel_id;

        if ($verificationStep && ! $actor->isManagerOrAbove()) {
            throw new AuthorizationException('Only a manager can verify or reject bookings.');
        }

        if (! $verificationStep && ! $actor->isManagerOrAbove() && ! $isAssignedStaff) {
            throw new AuthorizationException('Only the assigned staff member or a manager can update this appointment.');
        }

        // Free text is required for "Other" and for category-less cancels
        // (API, drawer) — predefined web-reject categories need none, since
        // the form only asks for custom text on "Other".
        $rejectCategory = $options['rejection_category'] ?? null;

        if ($verificationStep && $target === AppointmentStatus::Cancelled
            && ($rejectCategory === null || $rejectCategory === 'other')
            && empty($options['rejection_reason'])) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'A rejection reason is required when rejecting a booking.',
            ]);
        }

        DB::transaction(function () use ($appointment, $target, $actor, $options, $verificationStep) {
            $appointment->status = $target;
            $appointment->save();

            if ($verificationStep && $target === AppointmentStatus::Booked) {
                // Confirming verifies every still-pending payment — normally
                // just the one, but a top-up booking also carries its pending
                // topup row, which must count from here on (verifiedPaid()
                // sums Verified rows, driving the post-verify state).
                // Already-verified rows (settlements, partial receipts) and
                // rejected rows are never touched. Cash rows are never
                // verified either: cash is collected at the spa, so
                // confirming a Cash booking leaves its row pending (still
                // owed) instead of marking money as collected.
                foreach ($appointment->payments as $due) {
                    if ($due->status !== PaymentStatus::Pending) {
                        continue;
                    }

                    if ($due->method === 'cash') {
                        continue;
                    }

                    $due->update([
                        'status' => PaymentStatus::Verified,
                        'verified_by' => $actor->id,
                        'verified_at' => now(),
                    ]);
                }
            }

            if ($verificationStep && $target === AppointmentStatus::Cancelled) {
                $this->rejectPayment($appointment, $actor, $options);
            }

            if ($target === AppointmentStatus::Completed) {
                $this->recordCommission($appointment);
                $this->consumeItems($appointment, $options['items'] ?? [], $actor);
            }

            if ($appointment->user_id !== null && $appointment->user !== null) {
                $serviceName = $appointment->service->name ?? 'your service';

                $mailable = match (true) {
                    $verificationStep && $target === AppointmentStatus::Booked
                        => new PaymentVerifiedMail($appointment),
                    $verificationStep && $target === AppointmentStatus::Cancelled
                        => new PaymentRejectedMail(
                            $appointment,
                            $options['rejection_reason'] ?? null,
                            $options['rejection_category'] ?? null
                        ),
                    default => null,
                };

                $this->notificationService->notify(
                    $appointment->user,
                    'Appointment ' . $target->label(),
                    $options['notify_body'] ?? "Your booking for {$serviceName} is now \"{$target->label()}\".",
                    $mailable,
                    ['appointment_id' => $appointment->id, 'status' => $target->value]
                );
            }
        });

        return $appointment->fresh(['user', 'service', 'personnel', 'payment']);
    }

    /**
     * Marks the rejected payment row and records held money (step 2B).
     *
     * The row acted on is the payment the manager clicked (options
     * payment_id from PaymentController@reject) — normally the primary,
     * or a pending topup being rejected. Only a still-pending row is ever
     * flipped; a payment already verified (e.g. a branch settlement recorded
     * before verification) is never touched by a later cancel / no-show.
     *
     * Predefined reasons carry no free text (the web reject form only asks
     * for custom text on "Other"); category-less cancels (API, drawer) keep
     * storing the reason as before. amount_received only survives for
     * eligible categories.
     *
     * When a topup_eligible reason recorded money (> 0), a Verified
     * partial_receipt row is created for it — method and proof copied from
     * the rejected row — so verifiedPaid() (which sums Verified rows) keeps
     * reflecting what the branch actually holds. A top-up rejection also
     * folds its amount into the ORIGINAL (primary) payment's
     * amount_received, so the refundable total and the recomputed shortfall
     * always cover ALL money held.
     *
     * @param array{rejection_category?: string|null, rejection_reason?: string|null, amount_received?: float|int|string|null, payment_id?: int|null} $options
     */
    private function rejectPayment(Appointment $appointment, User $actor, array $options): void
    {
        $primary = $appointment->payment;
        $rejected = isset($options['payment_id'])
            ? $appointment->payments->find($options['payment_id'])
            : $primary;

        if (! $rejected || $rejected->appointment_id !== $appointment->id) {
            return;
        }

        if ($rejected->status !== PaymentStatus::Pending) {
            return;
        }

        $category = $options['rejection_category'] ?? null;
        $flags = $category ? config('payment.rejection_reasons.'.$category, []) : [];
        $amountEligible = ! empty($flags['topup_eligible']);

        $rejected->update([
            'status' => PaymentStatus::Rejected,
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'rejection_category' => $category,
            'rejection_reason' => ($category !== null && $category !== 'other')
                ? null
                : ($options['rejection_reason'] ?? null),
            'amount_received' => $amountEligible ? ($options['amount_received'] ?? null) : null,
        ]);

        $held = (float) ($rejected->fresh()->amount_received ?? 0);

        if ($category === null
            || empty($flags['topup_eligible'])
            || $held <= 0) {
            return;
        }

        $appointment->payments()->create([
            'kind' => 'partial_receipt',
            'amount' => $held,
            'method' => $rejected->method,
            'proof_path' => $rejected->proof_path,
            'status' => PaymentStatus::Verified,
            'verified_by' => $actor->id,
            'verified_at' => now(),
        ]);

        if ($primary && $primary->id !== $rejected->id) {
            $primary->increment('amount_received', $held);
        }
    }

    /**
     * Commission = the staff member's rate (default 10%) of the service's
     * listed price, locked in at completion time. firstOrCreate + the unique
     * appointment_id constraint keep this idempotent under retries.
     */
    private function recordCommission(Appointment $appointment): void
    {
        $servicePrice = (float) $appointment->service->price;
        $rate = (float) ($appointment->personnel?->staffProfile?->commission_rate ?? 10.00);

        Commission::firstOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'user_id' => $appointment->personnel_id,
                'service_price' => $servicePrice,
                'rate' => $rate,
                'amount' => round($servicePrice * $rate / 100, 2),
                'earned_at' => now(),
            ]
        );
    }

    /**
     * @param array<int, array{inventory_id: int, quantity: int}> $items
     */
    private function consumeItems(Appointment $appointment, array $items, User $actor): void
    {
        foreach ($items as $item) {
            $this->inventoryService->consume(
                (int) $item['inventory_id'],
                (int) $item['quantity'],
                StockMovement::TYPE_OUT,
                $appointment,
                $actor->id,
                'Consumed during service'
            );
        }
    }
}
