<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use App\Enums\ServiceLocationType;
use App\Models\Appointment;
use App\Models\AppointmentAddedService;
use App\Models\BusinessHours;
use App\Models\Service;
use App\Models\User;
use App\Support\BookingExtras;
use App\Support\PackageStaffSelection;
use App\Support\ServiceCapacity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Website Home Service Booking submission (Phase 3B). Mirrors
 * App\Http\Requests\StoreWebBranchBookingRequest exactly for the
 * service/personnel/date/time/payment-proof rules (same "any" → resolved
 * personnel_id trick, same exact-slot collision check) — the one addition is
 * the service address, required here since a Home Service appointment has
 * nowhere else to be. service_id is restricted to Home-Service-eligible
 * services only (App\Enums\ServiceLocationType::bookableAtHome()) so a
 * Branch-only service can never be booked through this endpoint even if a
 * request is crafted by hand.
 *
 * source_client_address_id is optional and purely informational (which saved
 * address, if any, this snapshot was taken from) — it is never used to look
 * up the address at display time; the submitted street/barangay/city/
 * province/postal_code fields are what get stored, exactly as typed, so
 * editing the booking address here never touches the Client's saved
 * client_addresses row (see BookingController::homeStore()).
 */
class StoreWebHomeBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Real authorization is the ['auth','role:4'] route middleware.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $service = $this->resolveService();

        if ($service?->requiresMultiStaff()) {
            $selection = PackageStaffSelection::fromRequest($this, $service);
            $this->merge([
                'staff' => $selection,
                'package_assignments' => PackageStaffSelection::toAssignments($selection),
                'personnel_id' => PackageStaffSelection::primaryPersonnelId($selection),
            ]);

            return;
        }

        if ($this->input('personnel_id') !== 'any') {
            return;
        }

        $this->merge(['personnel_id' => null]);
    }

    public function rules(): array
    {
        return [
            'service_id' => [
                'required',
                Rule::exists('services', 'id')
                    ->where('status', 'active')
                    ->whereIn('service_location_type', ServiceLocationType::bookableAtHome())
                    ->whereNull('deleted_at'),
            ],
            'personnel_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role_id', User::ROLE_STAFF)->whereNull('deleted_at'),
            ],
            'staff' => ['nullable', 'array'],
            'staff.*' => ['nullable'],
            'package_assignments' => ['nullable', 'array'],
            'variant_id' => [
                'nullable',
                Rule::exists('service_variants', 'id')->where('service_id', $this->input('service_id')),
            ],
            // Extra services taken in the SAME Home Service visit. Structural
            // rules only — prices are never accepted from the browser; store()
            // resolves each price from the real Service / ServiceVariant rows.
            'extras' => ['nullable', 'array'],
            'extras.*.service_id' => [
                'required',
                Rule::exists('services', 'id')
                    ->where('status', 'active')
                    ->whereIn('service_location_type', ServiceLocationType::bookableAtHome())
                    ->whereNull('deleted_at'),
            ],
            'extras.*.variant_label' => ['nullable', 'string', 'max:255'],
            'extras.*.personnel_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role_id', User::ROLE_STAFF)->whereNull('deleted_at'),
            ],
            // Every added service now has its OWN start time (validated
            // per-segment below against its own duration/personnel/capacity).
            'extras.*.start_time' => ['required', 'date_format:H:i'],
            'appointment_date' => 'required|date|after_or_equal:tomorrow',
            'start_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:1000',
            // Cash needs no proof (payable at the door); every other method
            // still needs the manual proof upload for the down payment or
            // full online amount.
            'payment_proof' => 'nullable|required_unless:method,cash|image|max:5120',
            'method' => ['sometimes', Rule::in(array_keys(config('payment.methods', [])))],

            // Service address — required every time; the Client may have
            // edited it away from their saved default (see the address step).
            'source_client_address_id' => [
                'nullable',
                Rule::exists('client_addresses', 'id')->where('user_id', $this->user()?->id)->whereNull('deleted_at'),
            ],
            'street_address' => 'required|string|max:255',
            'barangay' => 'required|string|max:100',
            'city_municipality' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:10|regex:/^[0-9]{4,10}$/',
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.exists' => 'That service is not bookable as a Home Service.',
            'personnel_id.exists' => 'Selected personnel is not available.',
            'variant_id.exists' => 'That package option is not available for this service.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateVariantChoice($validator);
            $this->validatePackageStaff($validator);
            $extras = $this->validateExtras($validator);
            $this->validateCashAgainstDownPayment($validator, $extras);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateSegments($validator, $extras);
        });
    }

    /**
     * Validate every service in the booking as its own segment: the main
     * service (the request's start_time) and every extra (its OWN start_time).
     * Each segment must fit business hours, avoid personnel overlap and fit
     * its resource pool at ITS window. Earlier segments in this same request
     * also occupy personnel and capacity, in order.
     *
     * @param  list<array{service: Service, variant_label: string|null, price: float, personnel_id: int|null, start_time: string|null}>  $extras
     */
    private function validateSegments(Validator $validator, array $extras): void
    {
        $service = $this->resolveService();
        if (! $service) {
            return;
        }

        $date = (string) $this->input('appointment_date');
        // Seats come from the chosen variant (packages), never from a raw
        // browser-sent guests count.
        $variant = $this->input('variant_id')
            ? $service->variants->firstWhere('id', (int) $this->input('variant_id'))
            : null;
        $segments = [[
            'service' => $service,
            'start' => (string) $this->input('start_time'),
            'duration' => max(1, (int) ($service->duration_minutes ?: 30)),
            'personnel' => $this->mainPersonnelIds(),
            'seats' => ServiceCapacity::seatsNeeded($variant?->guests),
            'window_key' => 'start_time',
            'personnel_key' => 'start_time',
            'main' => true,
        ]];

        foreach ($extras as $index => $extra) {
            $segments[] = [
                'service' => $extra['service'],
                'start' => (string) ($extra['start_time'] ?? ''),
                'duration' => max(1, (int) ($extra['service']->duration_minutes ?: 30)),
                'personnel' => $extra['personnel_id'] ? [(int) $extra['personnel_id']] : [],
                'seats' => 1,
                'window_key' => "extras.{$index}.start_time",
                'personnel_key' => "extras.{$index}.personnel_id",
                'main' => false,
            ];
        }

        $earlier = [];

        foreach ($segments as $segment) {
            $start = trim((string) $segment['start']);
            if (! preg_match('/^\d{1,2}:\d{2}/', $start)) {
                continue;
            }
            $start = substr($start, 0, 5);
            $startMinute = $this->minutesOf($start);

            $fits = BusinessHours::startFitsOnDate($date, $start, $segment['duration']);
            if (! $fits) {
                $validator->errors()->add(
                    $segment['window_key'],
                    'That time is past shop closing. Please choose an earlier slot, or book for another day.'
                );
            }

            $conflict = $segment['personnel'] !== [] && (
                $this->overlapsExisting($date, $startMinute, $segment['duration'], $segment['personnel'])
                || $this->overlapsEarlier($startMinute, $segment['duration'], $segment['personnel'], $earlier)
            );

            if ($conflict) {
                $validator->errors()->add(
                    $segment['personnel_key'],
                    $segment['main']
                        ? 'That slot was just taken. Please choose a different time.'
                        : 'That slot was just taken.'
                );
            }

            if ($fits && ! $conflict) {
                $this->validateSegmentCapacity($validator, $date, $segment, $startMinute, $earlier);
            }

            $earlier[] = [
                'service' => $segment['service'],
                'start' => $startMinute,
                'duration' => $segment['duration'],
                'personnel' => $segment['personnel'],
                'seats' => $segment['seats'],
            ];
        }
    }

    /** @return list<int> */
    private function mainPersonnelIds(): array
    {
        $assignments = $this->input('package_assignments');
        if (is_array($assignments)) {
            return collect($assignments)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        }

        $personnelId = $this->integer('personnel_id');

        return $personnelId ? [$personnelId] : [];
    }

    /**
     * @param  list<int>  $personnelIds
     */
    private function overlapsExisting(string $date, int $startMinute, int $duration, array $personnelIds): bool
    {
        $endMinute = $startMinute + $duration;

        $appointments = Appointment::query()
            ->with(['service:id,duration_minutes', 'personnelAssignments:id,appointment_id,user_id'])
            ->whereDate('appointment_date', $date)
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
            ])
            ->where(function ($query) use ($personnelIds) {
                $query->whereIn('personnel_id', $personnelIds)
                    ->orWhereHas('personnelAssignments', fn ($assignments) => $assignments->whereIn('user_id', $personnelIds));
            })
            ->get(['id', 'service_id', 'personnel_id', 'start_time']);

        foreach ($appointments as $appointment) {
            $ids = collect([$appointment->personnel_id])
                ->merge($appointment->personnelAssignments->pluck('user_id'))
                ->filter()
                ->map(fn ($id) => (int) $id);

            if ($ids->intersect($personnelIds)->isEmpty()) {
                continue;
            }

            $existingStart = $this->minutesOf((string) $appointment->start_time);
            $existingDuration = max(1, (int) ($appointment->service?->duration_minutes ?: 30));

            if ($startMinute < $existingStart + $existingDuration && $endMinute > $existingStart) {
                return true;
            }
        }

        $added = AppointmentAddedService::query()
            ->with(['service:id,duration_minutes', 'appointment:id,start_time'])
            ->whereIn('personnel_id', $personnelIds)
            ->whereHas('appointment', function ($query) use ($date) {
                $query->whereDate('appointment_date', $date)
                    ->whereNotIn('status', [
                        AppointmentStatus::Cancelled->value,
                        AppointmentStatus::NoShow->value,
                    ]);
            })
            ->get(['id', 'appointment_id', 'service_id', 'personnel_id', 'start_time']);

        foreach ($added as $row) {
            // Its own start_time when the booking wizard gave it one; a NULL
            // row keeps the legacy interpretation (it rides on its parent
            // appointment's start).
            $rowStart = $row->start_time
                ? $this->minutesOf($row->start_time->format('H:i'))
                : $this->minutesOf((string) $row->appointment?->start_time);

            $existingDuration = max(1, (int) ($row->service?->duration_minutes ?: 30));

            if ($startMinute < $rowStart + $existingDuration && $endMinute > $rowStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<int>  $personnelIds
     * @param  list<array{service: Service, start: int, duration: int, personnel: list<int>, seats: int}>  $earlier
     */
    private function overlapsEarlier(int $startMinute, int $duration, array $personnelIds, array $earlier): bool
    {
        $endMinute = $startMinute + $duration;

        foreach ($earlier as $previous) {
            if (array_intersect($personnelIds, $previous['personnel']) === []) {
                continue;
            }

            if ($startMinute < $previous['start'] + $previous['duration'] && $endMinute > $previous['start']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{service: Service, duration: int, seats: int, window_key: string}  $segment
     * @param  list<array{service: Service, start: int, duration: int, personnel: list<int>, seats: int}>  $earlier
     */
    private function validateSegmentCapacity(Validator $validator, string $date, array $segment, int $startMinute, array $earlier): void
    {
        $endMinute = $startMinute + max(1, $segment['duration']);

        foreach (ServiceCapacity::poolsForService($segment['service']) as $poolKey) {
            $limit = (int) (ServiceCapacity::pools()[$poolKey]['limit'] ?? 0);
            if ($limit < 1) {
                continue;
            }

            $used = ServiceCapacity::usedSeats($date, $startMinute, $segment['duration'], $poolKey);

            $earlierSeats = 0;
            foreach ($earlier as $previous) {
                if (! in_array($poolKey, ServiceCapacity::poolsForService($previous['service']), true)) {
                    continue;
                }
                if ($startMinute < $previous['start'] + $previous['duration'] && $endMinute > $previous['start']) {
                    $earlierSeats += $previous['seats'];
                }
            }

            if (($used + $earlierSeats + $segment['seats']) > $limit) {
                $validator->errors()->add(
                    $segment['window_key'],
                    ServiceCapacity::denialMessage($segment['service'])
                );

                return;
            }
        }
    }

    private function resolveService(): ?Service
    {
        $serviceId = $this->integer('service_id');

        return $serviceId
            ? Service::with(['variants', 'positions'])->find($serviceId)
            : null;
    }

    /**
     * Validate the extras rows through the SAME resolver store() uses and
     * return the server-trusted extras (empty when none/invalid). Any error
     * is added under the offending `extras.N.*` key, so the wizard shows its
     * normal inline message and nothing is written.
     *
     * @return list<array{service: \App\Models\Service, variant_label: string|null, price: float, personnel_id: int|null, start_time: string|null}>
     */
    private function validateExtras(Validator $validator): array
    {
        $rows = $this->input('extras', []);
        if (! is_array($rows) || $rows === []) {
            return [];
        }

        try {
            return BookingExtras::resolve($rows);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($key, $message);
                }
            }

            return [];
        }
    }

    private function validatePackageStaff(Validator $validator): void
    {
        $service = $this->resolveService();
        if (! $service?->requiresMultiStaff()) {
            return;
        }

        $selection = $this->input('staff', []);
        if (! is_array($selection) || ! PackageStaffSelection::isComplete($service, $selection)) {
            $validator->errors()->add('staff', 'Please choose Spa Personnel for every role in this package.');
        }
    }

    /**
     * A tiered service must carry a variant_id that belongs to it. The
     * foreign/invalid case is already rejected by the exists rule (scoped to
     * service_id); this only adds the friendlier "you must pick one" error.
     */
    private function validateVariantChoice(Validator $validator): void
    {
        $serviceId = $this->integer('service_id');
        $service = $serviceId ? \App\Models\Service::with('variants')->find($serviceId) : null;

        if (! $service || $service->variants->isEmpty()) {
            return;
        }

        if ($this->input('variant_id') === null || $this->input('variant_id') === '') {
            $validator->errors()->add('variant_id', 'Please choose a package option for this service.');
        }
    }

    /**
     * Cash is only offered when nothing is due online. When the booked
     * services (main + extras) add up to a down payment, it must be paid
     * online with proof — rejected server-side here so hiding the option in
     * the view alone can never be bypassed.
     *
     * @param  list<array{service: \App\Models\Service, price: float}>  $extras
     */
    private function validateCashAgainstDownPayment(Validator $validator, array $extras): void
    {
        if ($this->input('method') !== 'cash') {
            return;
        }

        $service = $this->resolveService();
        if (! $service) {
            return;
        }

        $variant = $this->input('variant_id')
            ? $service->variants->firstWhere('id', (int) $this->input('variant_id'))
            : null;
        $mainPrice = (float) ($variant?->price ?? $service->price);

        if (BookingExtras::downPayment($service, $mainPrice, $extras) > 0) {
            $validator->errors()->add(
                'method',
                'Cash is not available because this booking requires a down payment. Please pay the down payment online with proof.'
            );
        }
    }

    /** 'H:i' or 'H:i:s' clock time as minutes from midnight for window math. */
    private function minutesOf(string $time): int
    {
        $parsed = \Illuminate\Support\Carbon::parse($time);

        return ((int) $parsed->hour) * 60 + ((int) $parsed->minute);
    }

}
