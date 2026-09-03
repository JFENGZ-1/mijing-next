<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateSiteClosureRequest extends ApiFormRequest
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
            'reason' => ['sometimes', 'nullable', 'string', 'max:240'],
            'beginDate' => ['sometimes', 'date'],
            'endDate' => ['sometimes', 'date'],
            'status' => ['sometimes', 'string', Rule::in(['scheduled', 'active', 'completed', 'cancelled'])],
        ];
    }
}
