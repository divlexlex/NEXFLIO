<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use App\Models\AppointmentChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A Client raising a cancellation or reschedule request on one of their own
 * appointments (Website "My Appointments"). Nothing on the appointment
 * changes here — this only records the request for a Manager to approve
 * (App\Http\Controllers\Web\AppointmentRequestController). Route is behind
 * ['auth','role:4']; authorize() additionally pins it to the appointment's
 * owner.
 */
class StoreAppointmentChangeRequest extends FormRequest
{
    /** Statuses a client may still request a change on. */
    private const CHANGEABLE = [AppointmentStatus::Unverified, AppointmentStatus::Booked];

    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return $appointment !== null
            && $this->user() !== null
            && $appointment->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(AppointmentChangeRequest::TYPES)],
            'reason' => ['nullable', 'string', 'max:1000'],

            'requested_date' => ['nullable', 'required_if:type,reschedule', 'date', 'after_or_equal:today'],
            'requested_start_time' => ['nullable', 'required_if:type,reschedule', 'date_format:H:i'],
            'requested_personnel_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role_id', User::ROLE_STAFF)->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'requested_date.required_if' => 'Please pick the date you would like to move the appointment to.',
            'requested_start_time.required_if' => 'Please pick the time you would like to move the appointment to.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $appointment = $this->route('appointment');

            if (! in_array($appointment->status, self::CHANGEABLE, true)) {
                $validator->errors()->add(
                    'type',
                    'This appointment can no longer be changed here — please contact the branch.'
                );

                return;
            }

            if ($appointment->changeRequests()->where('status', AppointmentChangeRequest::STATUS_PENDING)->exists()) {
                $validator->errors()->add(
                    'type',
                    'You already have a pending request on this appointment. Wait for the team to review it first.'
                );

                return;
            }

            if ($this->input('type') === AppointmentChangeRequest::TYPE_RESCHEDULE) {
                $sameDate = $appointment->appointment_date->toDateString() === $this->input('requested_date');
                $sameTime = substr((string) $appointment->start_time, 0, 5) === $this->input('requested_start_time');

                if ($sameDate && $sameTime) {
                    $validator->errors()->add(
                        'requested_start_time',
                        'That is the same date and time as the current appointment.'
                    );
                }
            }
        });
    }
}
