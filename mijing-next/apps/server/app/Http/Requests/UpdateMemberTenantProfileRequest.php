<?php

namespace App\Http\Requests;

class UpdateMemberTenantProfileRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'displayName' => ['sometimes', 'required', 'string', 'max:80'],
            'avatarObjectKey' => ['sometimes', 'nullable', 'string', 'max:500'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:male,female,undisclosed'],
            'birthDate' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'heightCm' => ['sometimes', 'nullable', 'numeric', 'between:30,300'],
            'weightKg' => ['sometimes', 'nullable', 'numeric', 'between:2,500'],
            'mobile' => ['prohibited'],
            'nationalId' => ['prohibited'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
