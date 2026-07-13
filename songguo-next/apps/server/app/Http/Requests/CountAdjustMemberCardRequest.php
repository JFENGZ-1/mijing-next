<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CountAdjustMemberCardRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'count' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'commandKey' => ['required', 'uuid'],
            'correctsEntryId' => ['nullable', 'integer', 'min:1'],
            'allowNegative' => ['nullable', 'boolean'],
        ];
    }
}
