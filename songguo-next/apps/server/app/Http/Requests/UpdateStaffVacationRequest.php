<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateStaffVacationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'beginAt' => ['sometimes', 'date'],
            'endAt' => ['sometimes', 'date'],
            'groupBookingPolicy' => ['sometimes', 'string', Rule::in(['allow', 'block'])],
            'privateBookingPolicy' => ['sometimes', 'string', Rule::in(['allow', 'block'])],
            'remark' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'string', Rule::in(['scheduled', 'active', 'completed', 'cancelled'])],
        ];
    }
}
