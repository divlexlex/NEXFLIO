<?php

namespace App\Http\Requests;

use App\Models\LeaveRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => ['required', Rule::in(LeaveRequest::TYPES)],
            'reason' => 'required|string|max:1000',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $overlaps = LeaveRequest::query()
                ->where('user_id', $this->user()->id)
                ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
                ->whereDate('start_date', '<=', $this->input('end_date'))
                ->whereDate('end_date', '>=', $this->input('start_date'))
                ->exists();

            if ($overlaps) {
                $validator->errors()->add(
                    'start_date',
                    'You already have a pending or approved leave overlapping these dates.'
                );
            }
        });
    }
}
