<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use App\Enums\ServiceLocationType;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'appointment_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:1000',
            'payment_proof' => 'required|image|max:5120',
            'method' => ['sometimes', Rule::in(['gcash', 'bank_transfer', 'card'])],

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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->integer('personnel_id')) {
                return;
            }

            $taken = Appointment::query()
                ->where('personnel_id', $this->integer('personnel_id'))
                ->whereDate('appointment_date', $this->input('appointment_date'))
                ->where('start_time', $this->input('start_time'))
                ->whereNotIn('status', [
                    AppointmentStatus::Cancelled->value,
                    AppointmentStatus::NoShow->value,
                ])
                ->exists();

            if ($taken) {
                $validator->errors()->add(
                    'start_time',
                    'That slot was just taken. Please choose a different time.'
                );
            }
        });
    }

}
