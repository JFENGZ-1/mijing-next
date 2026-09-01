<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveCompensationRoleAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'roleId' => ['required', 'integer', 'min:1'],
            'staffId' => ['required', 'integer', 'min:1'],
            'effectiveFrom' => ['sometimes', 'nullable', 'date'],
            'effectiveUntil' => ['sometimes', 'nullable', 'date', 'after_or_equal:effectiveFrom'],
            'reason' => ['sometimes', 'nullable', 'string', 'min:2', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ];
    }
}
