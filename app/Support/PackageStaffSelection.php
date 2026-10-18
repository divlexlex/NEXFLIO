<?php

namespace App\Support;

use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Resolve / format the per-position staff picks for Packages bookings.
 *
 * Query + form shape: staff[position_id] = user_id|"any"
 * Regular (non-package) services keep using the flat `personnel` param.
 */
class PackageStaffSelection
{
    /**
     * @return array<int, string> position_id => "any"|numeric string
     */
    public static function fromRequest(Request $request, Service $service): array
    {
        if (! $service->requiresMultiStaff()) {
            return [];
        }

        $positions = $service->relationLoaded('positions')
            ? $service->positions
            : $service->positions()->orderBy('positions.id')->get();

        $raw = $request->input('staff', $request->query('staff', []));
        if (! is_array($raw)) {
            $raw = [];
        }

        $out = [];
        foreach ($positions as $position) {
            $value = $raw[$position->id] ?? $raw[(string) $position->id] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $out[(int) $position->id] = $value === 'any' ? 'any' : (string) (int) $value;
        }

        return $out;
    }

    /** True when every required package position has a pick (any or a user). */
    public static function isComplete(Service $service, array $selection): bool
    {
        if (! $service->requiresMultiStaff()) {
            return false;
        }

        $required = $service->relationLoaded('positions')
            ? $service->positions->pluck('id')
            : $service->positions()->pluck('positions.id');

        foreach ($required as $positionId) {
            if (! array_key_exists((int) $positionId, $selection)) {
                return false;
            }
        }

        return $required->isNotEmpty();
    }

    /**
     * position_id => user_id|null ready for Appointment::syncPersonnelAssignments.
     *
     * @param  array<int, string>  $selection
     * @return array<int, int|null>
     */
    public static function toAssignments(array $selection): array
    {
        $out = [];
        foreach ($selection as $positionId => $value) {
            $out[(int) $positionId] = $value === 'any' ? null : (int) $value;
        }

        return $out;
    }

    /** Primary personnel_id for the appointments column (first concrete pick). */
    public static function primaryPersonnelId(array $selection): ?int
    {
        foreach ($selection as $value) {
            if ($value !== 'any' && $value !== null && $value !== '') {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * Concrete user ids chosen (excludes "any") — used for slot collision.
     *
     * @param  array<int, string>  $selection
     * @return list<int>
     */
    public static function concreteIds(array $selection): array
    {
        return collect($selection)
            ->reject(fn ($value) => $value === 'any' || $value === null || $value === '')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $selection
     */
    public static function label(array $selection, Collection $positions): string
    {
        if ($selection === []) {
            return 'No preferred personnel';
        }

        $byId = $positions->keyBy('id');
        $parts = [];

        foreach ($selection as $positionId => $value) {
            $role = $byId->get($positionId)?->name ?? 'Staff';
            $name = $value === 'any'
                ? 'No preference'
                : (User::find($value)?->name ?? 'Selected');
            $parts[] = "{$role}: {$name}";
        }

        return implode(' · ', $parts);
    }

    /**
     * Query-string / hidden-field pairs to carry staff[...] forward.
     *
     * @param  array<int, string>  $selection
     * @return array<string, string>
     */
    public static function queryParams(array $selection): array
    {
        $params = [];
        foreach ($selection as $positionId => $value) {
            $params['staff['.$positionId.']'] = $value;
        }

        return $params;
    }
}
