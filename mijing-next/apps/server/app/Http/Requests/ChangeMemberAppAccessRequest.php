<?php

namespace App\Http\Requests;

class ChangeMemberAppAccessRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', 'in:allowed,blocked'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
