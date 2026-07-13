<?php

namespace App\Http\Requests;

class IssueMemberCardRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cardProductId' => ['required', 'integer', 'min:1'],
            'commandKey' => ['required', 'uuid'],
            'openingBalance' => ['nullable', 'numeric', 'min:0'],
            'openingCount' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('openingBalance') && $this->filled('openingCount')) {
                $validator->errors()->add('openingBalance', 'openingBalance 与 openingCount 不能同时提供');
            }
        });
    }
}
