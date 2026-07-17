<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'nullable|array',
            'items.*.inventory_id' => 'required|integer|exists:inventories,id',
            // Whole units only — no fractions of a bottle.
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }
}
