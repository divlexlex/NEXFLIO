<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Mail\PaymentRejectedMail;
use App\Mail\PaymentVerifiedMail;
use App\Models\Appointment;
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
     *     rejection_reason?: string|null,
     *     items?: array<int, array{inventory_id: int, quantity: int}>,
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

        if ($verificationStep && $target === AppointmentStatus::Cancelled && empty($options['rejection_reason'])) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'A rejection reason is required when rejecting a booking.',
            ]);
        }

        DB::transaction(function () use ($appointment, $target, $actor, $options, $verificationStep) {
            $appointment->status = $target;
            $appointment->save();

            if ($verificationStep && $appointment->payment) {
                $appointment->payment->update($target === AppointmentStatus::Booked
                    ? [
                        'status' => PaymentStatus::Verified,
                        'verified_by' => $actor->id,
                        'verified_at' => now(),
                    ]
                    : [
                        'status' => PaymentStatus::Rejected,
                        'verified_by' => $actor->id,
                        'verified_at' => now(),
                        'rejection_reason' => $options['rejection_reason'],
                    ]);
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
                        => new PaymentRejectedMail($appointment, $options['rejection_reason'] ?? null),
                    default => null,
                };

                $this->notificationService->notify(
                    $appointment->user,
                    'Appointment ' . $target->label(),
                    "Your booking for {$serviceName} is now \"{$target->label()}\".",
                    $mailable,
                    ['appointment_id' => $appointment->id, 'status' => $target->value]
                );
            }
        });

        return $appointment->fresh(['user', 'service', 'personnel', 'payment']);
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
