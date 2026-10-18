<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BusinessHours;
use App\Models\Service;
use App\Models\User;
use App\Support\ServiceCapacity;
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

            $service = Service::find($this->integer('service_id'));
            $date = $this->input('appointment_date');
            $time = $this->input('start_time');
            if ($service && $date && $time
                && ! BusinessHours::startFitsOnDate($date, $time, (int) $service->duration_minutes)) {
                $validator->errors()->add(
                    'start_time',
                    'That time is past shop closing. Please choose an earlier slot, or book for another day.'
                );
            }

            if ($service && $date && $time && ! $validator->errors()->has('start_time')
                && ! ServiceCapacity::hasCapacity($service, $date, $time, $this->integer('guests') ?: null)) {
                $validator->errors()->add('start_time', ServiceCapacity::denialMessage($service));
            }
        });
    }
}
