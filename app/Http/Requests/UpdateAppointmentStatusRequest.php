<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
            'rejection_reason' => 'nullable|string|max:1000',
        ];
    }

    public function targetStatus(): AppointmentStatus
    {
        return AppointmentStatus::from($this->input('status'));
    }
}
