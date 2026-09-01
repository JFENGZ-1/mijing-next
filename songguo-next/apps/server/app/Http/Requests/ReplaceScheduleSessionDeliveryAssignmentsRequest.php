<?php

namespace App\Http\Requests;

class ReplaceScheduleSessionDeliveryAssignmentsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'commandKey' => ['required', 'uuid'],
            'expectedVersion' => ['sometimes', 'integer', 'min:1'],
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.staffId' => ['required', 'integer', 'min:1'],
            'assignments.*.compensationRoleId' => ['required', 'integer', 'min:1'],
            'assignments.*.allocationBps' => ['sometimes', 'integer', 'between:1,10000'],
            'assignments.*.isPrimary' => ['sometimes', 'boolean'],
        ];
    }
}
