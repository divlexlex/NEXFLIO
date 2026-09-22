<?php

namespace App\Http\Requests;

use App\Enums\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'required|string|max:100',
            'middle_name'     => 'nullable|string|max:100',
            'contact_number'  => ['required', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'position'        => ['required', Rule::in(array_column(Position::cases(), 'value'))],
            'base_pay'        => 'required|numeric|min:0',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'hired_at'        => 'required|date',
        ];
    }

    public function messages(): array
    {
        return [
            'contact_number.regex' => 'Enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).',
            'position.in'          => 'Position must be Nail Technician, Massage Technician, or Facial Technician.',
        ];
    }
}
