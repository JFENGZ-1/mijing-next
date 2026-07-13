<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class BalanceAdjustMemberCardRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
            'commandKey' => ['required', 'uuid'],
            'correctsEntryId' => ['nullable', 'integer', 'min:1'],
            'allowNegative' => ['nullable', 'boolean'],
        ];
    }
}
