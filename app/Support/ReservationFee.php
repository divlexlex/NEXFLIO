<?php

namespace App\Support;

use App\Models\Service;

/**
 * Reservation fee (down payment) for EVERY service — the single server-side
 * calculation shared by every booking wizard step and by store()/homeStore().
 * A browser-supplied amount is never trusted: the fee is always re-derived
 * here from the real Service row and the chosen package tier label.
 * No category is exempt.
 *
 * Resolution order: a chosen package variant decides first. A mapped tier
 * label (Solo ₱300 / Couple ₱500 / Barkada ₱2,000 — see config/booking.php)
 * keeps its exact fee. Any OTHER variant label falls back to the same price
 * rule used for variant-less bookings, applied to the VARIANT's price:
 * booked price ₱500+ → flat ₱300, below ₱500 → exactly half the price
 * (2 decimals). Variant-less bookings use that price rule directly. The fee
 * therefore always resolves for a chosen variant.
 *
 * See config/booking.php for the tier amounts.
 */
class ReservationFee
{
    /**
     * The reservation fee due now. Always resolves: a mapped tier label uses
     * its tier amount, any other variant label (or no variant) uses the price
     * rule on the booked price. Never exceeds the booked price as a safety
     * net: a fee at or above the price simply means the whole booking is paid
     * online (no balance).
     */
    public static function for(Service $service, ?string $variantLabel = null, ?int $guests = null): float
    {
        $price = static::priceFor($service, $variantLabel);

        if ($variantLabel !== null && $variantLabel !== '') {
            $tier = static::tier($variantLabel, null);
            $tiers = (array) config('booking.reservation_fee.tiers', []);

            // Mapped labels keep their exact current fee and take priority.
            if ($tier !== null && isset($tiers[$tier])) {
                return min((float) $tiers[$tier], $price);
            }

            // Unmapped label: same price rule as a variant-less service, on
            // the variant's own price.
            return static::priceRule($price);
        }

        return static::priceRule($price);
    }

    /**
     * The variant-less price rule: ₱500+ → flat ₱300, below ₱500 → exactly
     * half (2 decimals); never more than the price itself.
     */
    private static function priceRule(float $price): float
    {
        $fee = $price >= 500 ? 300.0 : round($price / 2, 2);

        return min($fee, $price);
    }

    /**
     * Package tier name from the chosen variant label (Solo->Single,
     * Couple->Couple, Barkada->Barkada). Null when unmapped. The $guests
     * argument is retained for call-site compatibility but no longer
     * affects the fee.
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
