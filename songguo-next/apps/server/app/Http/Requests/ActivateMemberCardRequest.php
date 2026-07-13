<?php

namespace App\Http\Requests;

class ActivateMemberCardRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'commandKey' => ['nullable', 'uuid'],
        ];
    }
}
