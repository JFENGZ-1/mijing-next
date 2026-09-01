<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceMemberCardShareAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assignments' => ['required', 'array', 'max:50'],
            'assignments.*.staffId' => ['required', 'integer', 'min:1'],
            'assignments.*.compensationRoleId' => ['required', 'integer', 'min:1'],
            'assignments.*.allocationBps' => ['required', 'integer', 'min:1', 'max:10000'],
            'assignments.*.effectiveFrom' => ['required', 'date_format:Y-m-d'],
            'assignments.*.effectiveUntil' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'expectedVersion' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:2', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ];
    }
}
