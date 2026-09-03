<?php

namespace App\Http\Requests;

class UpdateMemberProfileRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'displayName' => ['required', 'string', 'max:80'],
            'avatarObjectKey' => ['nullable', 'string', 'max:500'],
            'gender' => ['nullable', 'string', 'in:male,female,undisclosed'],
            'birthDate' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'heightCm' => ['nullable', 'numeric', 'between:30,300'],
            'weightKg' => ['nullable', 'numeric', 'between:2,500'],
            'version' => ['nullable', 'integer', 'min:1'],
            'acceptedDocumentIds' => ['required', 'array'],
            'acceptedDocumentIds.*' => ['integer', 'distinct', 'min:1'],
        ];
    }
}
