<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => [
                'required',
                Rule::exists('services', 'id')->where('status', 'active')->whereNull('deleted_at'),
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
                    'This staff member already has a booking at that date and time.'
                );
            }
        });
    }
}
