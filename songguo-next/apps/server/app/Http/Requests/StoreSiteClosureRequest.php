<?php

namespace App\Http\Requests;

class StoreSiteClosureRequest extends ApiFormRequest
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
            'beginDate' => ['required', 'date'],
            'endDate' => ['required', 'date'],
        ];
    }
}
