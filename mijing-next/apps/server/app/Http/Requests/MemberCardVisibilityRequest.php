<?php

namespace App\Http\Requests;

class MemberCardVisibilityRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
            'commandKey' => ['nullable', 'uuid'],
        ];
    }
}
