<?php

namespace App\Http\Requests;

class StoreStaffMemberRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:80'],
            'mobile' => ['nullable', 'string', 'max:24', 'regex:/^\+?[0-9 -]{7,24}$/'],
            'gender' => ['nullable', 'string', 'in:male,female,undisclosed'],
            'birthDate' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'assignToMe' => ['sometimes', 'boolean'],
        ];
    }
}
