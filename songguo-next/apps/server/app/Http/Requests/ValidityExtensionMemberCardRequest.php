<?php

namespace App\Http\Requests;

class ValidityExtensionMemberCardRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'extendDays' => ['required_without:validUntil', 'nullable', 'integer', 'min:1'],
            'validUntil' => ['required_without:extendDays', 'nullable', 'date'],
            'reason' => ['required', 'string', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ];
    }
}
