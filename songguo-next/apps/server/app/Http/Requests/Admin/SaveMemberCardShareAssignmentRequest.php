<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveMemberCardShareAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'memberCardId' => ['required', 'integer', 'min:1'],
            'compensationRoleId' => ['required', 'integer', 'min:1'],
            'staffId' => ['required', 'integer', 'min:1'],
            'allocationBps' => ['required', 'integer', 'min:1', 'max:10000'],
            'effectiveFrom' => ['sometimes', 'nullable', 'date'],
            'effectiveUntil' => ['sometimes', 'nullable', 'date', 'after_or_equal:effectiveFrom'],
            'expectedVersion' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:2', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ];
    }
}
