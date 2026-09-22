<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use App\Enums\ServiceLocationType;
use App\Models\Appointment;
use App\Models\LeaveRequest;
use App\Models\User;
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
        if ($this->input('personnel_id') !== 'any') {
            return;
        }

        $date = $this->input('appointment_date');
        $time = $this->input('start_time');

        if (! $date || ! $time) {
            // Leave as-is; the normal required/exists rules below will fail
            // on the missing date/time and report that instead.
            return;
        }

        $resolved = $this->firstAvailablePersonnelId($date, $time);

        $this->merge(['personnel_id' => $resolved]);
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
                'required',
                Rule::exists('users', 'id')->where('role_id', User::ROLE_STAFF)->whereNull('deleted_at'),
            ],
            'appointment_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:1000',
            'payment_proof' => 'required|image|max:5120',
            'method' => ['sometimes', Rule::in(['gcash', 'bank_transfer', 'card'])],
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.exists' => 'That service is not bookable at the branch.',
            'personnel_id.required' => 'No personnel are available for that date and time. Please choose a different time.',
            'personnel_id.exists' => 'No personnel are available for that date and time. Please choose a different time.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
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

    /**
     * Same eligibility rule as API\AppointmentController@personnel (active
     * staff profile, not currently on break, no approved leave covering the
     * date), narrowed to whoever additionally has no conflicting appointment
     * at this exact date/time (same collision rule StoreAppointmentRequest
     * uses). Returns null when nobody qualifies, which the normal
     * required/exists rules then reject with a clear message.
     */
    private function firstAvailablePersonnelId(string $date, string $time): ?int
    {
        $eligible = User::where('role_id', User::ROLE_STAFF)
            ->whereHas('staffProfile', function ($query) {
                $query->where('employment_status', 'active')
                    ->where('is_on_break', false);
            })
            ->whereDoesntHave('leaveRequests', function ($leaves) use ($date) {
                $leaves->where('status', LeaveRequest::STATUS_APPROVED)
                    ->whereDate('start_date', '<=', $date)
                    ->whereDate('end_date', '>=', $date);
            })
            ->pluck('id');

        if ($eligible->isEmpty()) {
            return null;
        }

        $busy = Appointment::query()
            ->whereIn('personnel_id', $eligible)
            ->whereDate('appointment_date', $date)
            ->where('start_time', $time)
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
            ])
            ->pluck('personnel_id');

        return $eligible->diff($busy)->first();
    }
}
