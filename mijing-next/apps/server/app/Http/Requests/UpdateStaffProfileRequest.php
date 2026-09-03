<?php

namespace App\Http\Requests;

class UpdateStaffProfileRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'displayName' => ['sometimes', 'required', 'string', 'max:80'],
            'avatarUrl' => ['sometimes', 'nullable', 'string', 'max:500'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
