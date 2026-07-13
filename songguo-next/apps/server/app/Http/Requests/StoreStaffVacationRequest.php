<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreStaffVacationRequest extends ApiFormRequest
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
            'beginAt' => ['required', 'date'],
            'endAt' => ['required', 'date', 'after:beginAt'],
            'groupBookingPolicy' => ['sometimes', 'string', Rule::in(['allow', 'block'])],
            'privateBookingPolicy' => ['sometimes', 'string', Rule::in(['allow', 'block'])],
            'remark' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
