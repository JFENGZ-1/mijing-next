<?php

namespace App\Http\Requests;

class OrderAmountCorrectionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gte:0'],
            'reason' => ['required', 'string', 'max:500'],
            'commandKey' => ['required', 'uuid'],
            'correctsEntryId' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
