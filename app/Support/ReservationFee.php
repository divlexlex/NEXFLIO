<?php

namespace App\Support;

use App\Models\Service;

/**
 * Reservation fee (down payment) for EVERY service — the single server-side
 * calculation shared by every booking wizard step and by store()/homeStore().
 * A browser-supplied amount is never trusted: the fee is always re-derived
 * here from the real Service row, the chosen package tier label and the
 * guest count. No category is exempt.
 *
 * See config/booking.php for the tier amounts.
 */
class ReservationFee
{
    /** Every service takes a reservation fee — no category is exempt. */
    public static function appliesTo(Service $service): bool
    {
        return true;
    }

    /**
     * Flat fee for specially-priced services, matched by exact service
     * name (config booking.reservation_fee.flat_fees). Returns the amount,
     * or null when the service has no flat fee.
     */
    public static function flatFeeFor(Service $service): ?float
    {
        $flatFees = (array) config('booking.reservation_fee.flat_fees', []);

        return array_key_exists($service->name, $flatFees)
            ? (float) $flatFees[$service->name]
            : null;
    }

    /**
     * The reservation fee due now, or null when the accommodation can't be
     * determined yet. Flat-fee services pay min(flat amount, booked price)
     * regardless of variant or guests; everything else resolves through
     * the tier table. Never exceeds the booked price: a tier amount at or
     * above the price simply means the whole booking is paid online (no
     * balance).
     */
    public static function for(Service $service, ?string $variantLabel = null, ?int $guests = null): ?float
    {
        if (! static::appliesTo($service)) {
            return null;
        }

        $flat = static::flatFeeFor($service);
        if ($flat !== null) {
            return min($flat, static::priceFor($service, $variantLabel));
        }

        $tier = static::tier($variantLabel, $guests);
        $tiers = (array) config('booking.reservation_fee.tiers', []);

        if ($tier === null || ! isset($tiers[$tier])) {
            return null;
        }

        return min((float) $tiers[$tier], static::priceFor($service, $variantLabel));
    }

    /**
     * Accommodation tier name. A package variant decides it
     * (Solo->Single, Couple->Couple, Barkada->Barkada); otherwise the guest
     * count does (1->Single, 2->Couple, 3+->Barkada). Null when undetermined.
     */
    public static function tier(?string $variantLabel, ?int $guests): ?string
    {
        if ($variantLabel !== null && $variantLabel !== '') {
            return match (strtolower($variantLabel)) {
                'solo' => 'Single',
                'couple' => 'Couple',
                'barkada' => 'Barkada',
                default => null,
            };
        }

        if ($guests === null) {
            return null;
        }

        return match (true) {
            $guests <= 1 => 'Single',
            $guests === 2 => 'Couple',
            default => 'Barkada',
        };
    }

    /** The booked price: the variant's price when a label is given, else the service's. */
    private static function priceFor(Service $service, ?string $variantLabel): float
    {
        if ($variantLabel !== null && $variantLabel !== '') {
            $variant = $service->variants->firstWhere('label', $variantLabel);
            if ($variant) {
                return (float) $variant->price;
            }
        }

        return (float) $service->price;
    }
}
