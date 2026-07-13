<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmMemberFieldPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.key' => [
                'required',
                'string',
                Rule::in(['mobile', 'name', 'gender', 'birthDate', 'nationalId', 'heightCm', 'weightKg', 'ownerStaffId']),
            ],
            'fields.*.isRequired' => ['sometimes', 'boolean'],
            'fields.*.isVisible' => ['sometimes', 'boolean'],
            'fields.*.staffEditable' => ['sometimes', 'boolean'],
        ];
    }
}
