<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use App\Enums\ServiceLocationType;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Support\PackageStaffSelection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Website Branch Booking submission (Phase 3A). Mirrors
 * App\Http\Requests\StoreAppointmentRequest (the mobile app's proven
 * booking-creation rules — same service/personnel/date/time/payment-proof
 * validation, same exact-slot double-booking check) exactly, rather than
 * modifying that shared class — StoreAppointmentRequest stays untouched so
 * the mobile app's booking path has zero regression risk.
 *
 * The one addition: the Website lets a Client pick "No preference" instead
 * of a specific Spa Personnel (personnel_id arrives here as the literal
 * string "any"). prepareForValidation() resolves that to a real, available
 * staff id — using the exact same eligibility rules as
 * API\AppointmentController@personnel and the exact same conflict check as
 * StoreAppointmentRequest — before the normal rules run, so personnel_id is
 * always a concrete id by the time it reaches the database (the appointments
 * table has no nullable/unassigned state).
 */
class StoreWebBranchBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Real authorization is the ['auth','role:4'] route middleware.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $service = $this->resolveService();

        // Packages: staff[position_id] = user_id|"any" → concrete assignments
        // + a primary personnel_id for the legacy column.
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

        // "No preference" — leave personnel unassigned so the manager can
        // assign the appropriate staff member later.
        $this->merge(['personnel_id' => null]);
    }

    public function rules(): array
    {
        return [
            'service_id' => [
                'required',
                Rule::exists('services', 'id')
                    ->where('status', 'active')
                    ->whereIn('service_location_type', ServiceLocationType::bookableAtBranch())
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
            'appointment_date' => 'required|date|after_or_equal:tomorrow',
            'start_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:1000',
            // Cash needs no proof (paid at the branch); every other method
            // still needs the manual proof upload. The `sometimes` default
            // stays gcash in BookingController@store, so a missing method
            // still requires proof via required_unless below.
            'payment_proof' => 'nullable|required_unless:method,cash|image|max:5120',
            'method' => ['sometimes', Rule::in(array_keys(config('payment.methods', [])))],
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.exists' => 'That service is not bookable at the branch.',
            'personnel_id.exists' => 'Selected personnel is not available.',
            'variant_id.exists' => 'That package option is not available for this service.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateVariantChoice($validator);
            $this->validatePackageCash($validator);
            $this->validatePackageStaff($validator);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $staffIds = $this->collisionStaffIds();
            if ($staffIds === []) {
                return;
            }

            $taken = Appointment::query()
                ->whereDate('appointment_date', $this->input('appointment_date'))
                ->where('start_time', $this->input('start_time'))
                ->whereNotIn('status', [
                    AppointmentStatus::Cancelled->value,
                    AppointmentStatus::NoShow->value,
                ])
                ->where(function ($query) use ($staffIds) {
                    $query->whereIn('personnel_id', $staffIds)
                        ->orWhereHas('personnelAssignments', function ($assignments) use ($staffIds) {
                            $assignments->whereIn('user_id', $staffIds);
                        });
                })
                ->exists();

            if ($taken) {
                $validator->errors()->add(
                    'start_time',
                    'That slot was just taken. Please choose a different time.'
                );
            }
        });
    }

    private function resolveService(): ?Service
    {
        $serviceId = $this->integer('service_id');

        return $serviceId
            ? Service::with(['variants', 'positions'])->find($serviceId)
            : null;
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

    /** @return list<int> */
    private function collisionStaffIds(): array
    {
        $assignments = $this->input('package_assignments');
        if (is_array($assignments)) {
            return collect($assignments)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        }

        $personnelId = $this->integer('personnel_id');

        return $personnelId ? [$personnelId] : [];
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
     * Package bookings (the booked service has variants) must pay the
     * reservation fee online with proof — Cash is rejected server-side, so
     * hiding the option in the view alone is never enough to bypass it.
     */
    private function validatePackageCash(Validator $validator): void
    {
        if ($this->input('method') !== 'cash') {
            return;
        }

        $serviceId = $this->integer('service_id');
        $service = $serviceId ? \App\Models\Service::with('variants')->find($serviceId) : null;

        if ($service && $service->variants->isNotEmpty()) {
            $validator->errors()->add('method', 'Cash is not available for package bookings.');
        }
    }

}
