<?php

namespace App\Support;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Support\Carbon;

/**
 * Branch resource limits by service category pool:
 *  - Massage beds: 2 concurrent clients
 *  - Head spa beds: 4 concurrent clients
 *  - Aesthetics: 1 concurrent client (Facial has no bed limit)
 *
 * Capacity is duration-aware: two appointments overlap when their
 * [start, start+duration) windows intersect. Home Service (and any
 * category without a pool) is not limited here.
 */
class ServiceCapacity
{
    /** @return array<string, array{label: string, limit: int, categories: list<string>}> */
    public static function pools(): array
    {
        return config('booking.concurrent_capacity', []);
    }

    public static function poolKeyForCategory(?string $category): ?string
    {
        if ($category === null || $category === '') {
            return null;
        }

        foreach (static::pools() as $key => $pool) {
            if (in_array($category, $pool['categories'] ?? [], true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Resource pools a booking of this service consumes. Packages may span
     * several (e.g. Facial + Massage inclusions); regular services use one.
     *
     * @return list<string>
     */
    public static function poolsForService(Service $service): array
    {
        if ($service->isPackageService()) {
            return $service->packageComponentServices()
                ->map(fn (Service $component) => static::poolKeyForCategory($component->category))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $key = static::poolKeyForCategory($service->category);

        return $key ? [$key] : [];
    }

    public static function seatsNeeded(?int $guests): int
    {
        return max(1, (int) ($guests ?: 1));
    }

    /**
     * True when every pool this service uses still has room for $guests
     * seats over the proposed start + duration window.
     */
    public static function hasCapacity(
        Service $service,
        string $date,
        string $startTime,
        ?int $guests = null,
        ?int $excludeAppointmentId = null,
        ?int $startMinute = null
    ): bool {
        $pools = static::poolsForService($service);
        if ($pools === []) {
            return true;
        }

        $duration = max(1, (int) ($service->duration_minutes ?: 30));
        // Optional override lets walk-in overnight starts (00:00 → 1440) share
        // this check without changing online callers' clock parsing.
        $startMinute ??= static::minutesOf($startTime);
        $seats = static::seatsNeeded($guests);

        foreach ($pools as $poolKey) {
            $limit = (int) (static::pools()[$poolKey]['limit'] ?? 0);
            if ($limit < 1) {
                continue;
            }

            $used = static::usedSeats($date, $startMinute, $duration, $poolKey, $excludeAppointmentId);
            if (($used + $seats) > $limit) {
                return false;
            }
        }

        return true;
    }

    public static function denialMessage(Service $service): string
    {
        return 'That time is fully booked. Please choose another slot.';
    }

    /**
     * Seats already occupied in $poolKey that overlap [start, start+duration).
     */
    public static function usedSeats(
        string $date,
        int $startMinute,
        int $durationMinutes,
        string $poolKey,
        ?int $excludeAppointmentId = null
    ): int {
        $pool = static::pools()[$poolKey] ?? null;
        if ($pool === null) {
            return 0;
        }

        $categories = $pool['categories'] ?? [];
        if ($categories === []) {
            return 0;
        }

        $endMinute = $startMinute + max(1, $durationMinutes);

        $appointments = Appointment::query()
            ->with('service:id,category,duration_minutes,package_details')
            ->whereDate('appointment_date', $date)
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
            ])
            ->when($excludeAppointmentId, fn ($q) => $q->where('id', '!=', $excludeAppointmentId))
            ->whereHas('service', function ($query) use ($categories) {
                $query->whereIn('category', $categories)
                    ->orWhere('category', 'Packages');
            })
            ->get(['id', 'service_id', 'start_time', 'guests']);

        $used = 0;
        foreach ($appointments as $appointment) {
            $service = $appointment->service;
            if (! $service) {
                continue;
            }

            // Count only appointments that actually consume this pool.
            if (! in_array($poolKey, static::poolsForService($service), true)) {
                continue;
            }

            $existingStart = static::minutesOf((string) $appointment->start_time);
            $existingDuration = max(1, (int) ($service->duration_minutes ?: 30));
            $existingEnd = $existingStart + $existingDuration;

            if ($startMinute < $existingEnd && $endMinute > $existingStart) {
                $used += static::seatsNeeded($appointment->guests);
            }
        }

        return $used;
    }

    private static function minutesOf(string $time): int
    {
        $parsed = Carbon::parse($time);

        return ((int) $parsed->hour) * 60 + ((int) $parsed->minute);
    }
}
