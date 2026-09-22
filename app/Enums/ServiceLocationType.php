<?php

namespace App\Enums;

/**
 * Where a Service can be booked — the real, server-enforced Home Service
 * eligibility flag Phase 3B needed and the schema didn't have (see the
 * Phase 3B resume audit's "STOP" report: category was only ever a display
 * label, never an eligibility signal). Branch Booking accepts Branch and
 * Both; Home Service Booking accepts Home and Both — enforced in
 * BookingController's requireService() and both Store*BookingRequest
 * classes, never only in the frontend.
 */
enum ServiceLocationType: string
{
    case Branch = 'branch';
    case Home = 'home';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Branch => 'Branch only',
            self::Home => 'Home Service only',
            self::Both => 'Branch or Home Service',
        };
    }

    /** @return array<string> */
    public static function bookableAtBranch(): array
    {
        return [self::Branch->value, self::Both->value];
    }

    /** @return array<string> */
    public static function bookableAtHome(): array
    {
        return [self::Home->value, self::Both->value];
    }
}
