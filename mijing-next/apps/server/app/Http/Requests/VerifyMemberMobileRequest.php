<?php

namespace App\Http\Requests;

class VerifyMemberMobileRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:1', 'max:128', 'not_in:test'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
