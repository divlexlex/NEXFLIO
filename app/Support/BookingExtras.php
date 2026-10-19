<?php

namespace App\Support;

use App\Models\Position;
use App\Models\Service;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Extra services taken in the SAME online branch booking visit (Problem #4).
 *
 * One place for the rules the wizard's request, its controller, and the
 * review/payment steps all need:
 *  - an extra is always a real, active, branch-bookable REGULAR service
 *    (never a package — packages are a main-service choice only),
 *  - its price is ALWAYS resolved server-side (variant price when the
 *    service has variants, otherwise the service price); a browser-sent
 *    price is never read,
 *  - its personnel must follow the same category→position eligibility rule
 *    the main service uses.
 *
 * The visit runs on the main booking's date and start time, so its total
 * duration is the main service's duration plus every extra's duration — the
 * value the business-hours window check must use.
 */
class BookingExtras
{
    /**
     * Resolve raw submitted rows (service_id, variant_label, personnel_id)
     * into server-trusted extras. Throws a ValidationException keyed to the
     * offending row so the wizard shows its normal inline error.
     *
     * @param  array<int, mixed>  $rows
     * @return list<array{service: Service, variant_label: string|null, price: float, down_payment: float, personnel_id: int|null}>
     *
     * @throws ValidationException
     */
    public static function resolve(array $rows): array
    {
        $resolved = [];

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $resolved[] = static::resolveRow($row, $index);
        }

        return $resolved;
    }

    /**
     * Resolve one submitted extra row. Throws keyed to $index so the wizard
     * can show its normal inline error for that row.
     *
     * @param  array<string, mixed>  $row
     * @return array{service: Service, variant_label: string|null, price: float, down_payment: float, personnel_id: int|null}
     *
     * @throws ValidationException
     */
    public static function resolveRow(array $row, int $index = 0): array
    {
        $service = Service::with(['variants', 'positions'])->find($row['service_id'] ?? null);

        if ($service === null) {
            throw ValidationException::withMessages([
                "extras.{$index}.service_id" => 'That extra service is not available.',
            ]);
        }

        if ($service->isPackageForExtras()) {
            throw ValidationException::withMessages([
                "extras.{$index}.service_id" => 'Packages cannot be added as extra services — pick a regular service.',
            ]);
        }

        $variantLabel = null;
        if ($service->variants->isNotEmpty()) {
            $label = $row['variant_label'] ?? null;
            $variant = $label !== null && $label !== ''
                ? $service->variants->firstWhere('label', $label)
                : null;

            if ($variant === null) {
                throw ValidationException::withMessages([
                    "extras.{$index}.variant_label" => 'Please choose a valid variant for this service.',
                ]);
            }

            $variantLabel = $variant->label;
            $price = (float) $variant->price;
        } else {
            $price = (float) $service->price;
        }

        $personnelId = isset($row['personnel_id']) && $row['personnel_id'] !== ''
            ? (int) $row['personnel_id']
            : null;

        $allowedPositions = Position::namesForServiceCategory((string) $service->category);
        if ($personnelId !== null) {
            if ($allowedPositions === []) {
                throw ValidationException::withMessages([
                    "extras.{$index}.personnel_id" => 'This service category has no eligible staff — leave the staff field empty.',
                ]);
            }

            $holds = User::whereKey($personnelId)
                ->whereHas('staffProfile', fn ($query) => $query->whereIn('position', $allowedPositions))
                ->exists();

            if (! $holds) {
                throw ValidationException::withMessages([
                    "extras.{$index}.personnel_id" => 'That staff member does not offer this service category.',
                ]);
            }
        }

        return [
            'service' => $service,
            'variant_label' => $variantLabel,
            'price' => $price,
            'down_payment' => static::downPaymentForService($service, $price),
            'personnel_id' => $personnelId,
        ];
    }

    /**
     * Extras total price (already folded into booked_price).
     *
     * @param  list<array{price: float}>  $extras
     */
    public static function totalPrice(array $extras): float
    {
        return round(array_sum(array_column($extras, 'price')), 2);
    }

    /**
     * Visit length: main duration + Σ extra durations.
     *
     * @param  list<array{service: Service}>  $extras
     */
    public static function totalDuration(Service $mainService, array $extras): int
    {
        return (int) ($mainService->duration_minutes ?? 0)
            + array_sum(array_map(fn (array $extra) => (int) $extra['service']->duration_minutes, $extras));
    }

    /**
     * The down payment configured for ONE booked service, never above what
     * that service costs in the booking. A NULL configuration means none.
     * This is the same rule every booking path and the Review / Payment
     * steps use, so a browser-sent amount is never trusted.
     */
    public static function downPaymentForService(Service $service, float $price): float
    {
        if ($service->down_payment === null) {
            return 0.0;
        }

        return round(min(max(0.0, (float) $service->down_payment), max(0.0, $price)), 2);
    }

    /**
     * Whole-visit down payment: the main service's configured amount plus
     * each extra's, every value already capped at its own resolved price.
     *
     * @param  list<array{service: Service, price: float}>  $extras
     */
    public static function downPayment(Service $mainService, float $mainPrice, array $extras): float
    {
        $total = static::downPaymentForService($mainService, $mainPrice);

        foreach ($extras as $extra) {
            $total += static::downPaymentForService($extra['service'], (float) $extra['price']);
        }

        return round($total, 2);
    }
}
