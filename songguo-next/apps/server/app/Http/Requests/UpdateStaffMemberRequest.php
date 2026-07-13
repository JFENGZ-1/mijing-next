<?php

namespace App\Http\Requests;

class UpdateStaffMemberRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'name' => ['sometimes', 'string', 'max:80'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:24', 'regex:/^\+?[0-9 -]{7,24}$/'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:male,female,undisclosed'],
            'birthDate' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
