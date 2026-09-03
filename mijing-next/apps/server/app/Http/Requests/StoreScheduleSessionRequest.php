<?php

namespace App\Http\Requests;

class StoreScheduleSessionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'courseId' => ['required', 'integer', 'min:1'],
            'roomId' => ['nullable', 'integer', 'min:1'],
            'coachStaffId' => ['required', 'integer', 'min:1'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
            'capacity' => ['required', 'integer', 'min:1'],
            'sessionKind' => ['required', 'string', 'in:group,private'],
            'deliveryAssignments' => ['sometimes', 'array', 'min:1'],
            'deliveryAssignments.*.staffId' => ['required', 'integer', 'min:1'],
            'deliveryAssignments.*.compensationRoleId' => ['required', 'integer', 'min:1'],
            'deliveryAssignments.*.allocationBps' => ['required', 'integer', 'min:1', 'max:10000'],
            'deliveryAssignments.*.isPrimary' => ['sometimes', 'boolean'],
            'assignmentCommandKey' => ['required_with:deliveryAssignments', 'uuid'],
        ];
    }
}
