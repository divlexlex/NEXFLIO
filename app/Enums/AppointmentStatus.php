<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Unverified = 'unverified';
    case Booked = 'booked';
    case InService = 'in-service';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no-show';

    /**
     * @return array<self> statuses this one may move to
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Unverified => [self::Booked, self::Cancelled],
            self::Booked => [self::InService, self::Cancelled, self::NoShow],
            self::InService => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled, self::NoShow => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->transitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->transitions() === [];
    }

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Pending verification',
            self::Booked => 'Booked',
            self::InService => 'In service',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No-show',
        };
    }
}
