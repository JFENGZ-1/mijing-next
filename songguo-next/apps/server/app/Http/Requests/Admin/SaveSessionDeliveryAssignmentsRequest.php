<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveSessionDeliveryAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assignments' => ['required', 'array', 'max:20'],
            'assignments.*.staffId' => ['required', 'integer', 'min:1'],
            'assignments.*.compensationRoleId' => ['required', 'integer', 'min:1'],
            'assignments.*.allocationBps' => ['required', 'integer', 'min:1', 'max:10000'],
            'assignments.*.isPrimary' => ['required', 'boolean'],
            'expectedVersion' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:4', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ];
    }
}
