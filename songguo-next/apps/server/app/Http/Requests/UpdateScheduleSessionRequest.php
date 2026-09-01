<?php

namespace App\Http\Requests;

class UpdateScheduleSessionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'courseId' => ['sometimes', 'integer', 'min:1'],
            'roomId' => ['nullable', 'integer', 'min:1'],
            'coachStaffId' => ['sometimes', 'integer', 'min:1'],
            'startsAt' => ['sometimes', 'date'],
            'endsAt' => ['sometimes', 'date'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'sessionKind' => ['sometimes', 'string', 'in:group,private'],
            'displayColor' => ['sometimes', 'nullable', 'string', 'max:24'],
            'acknowledgeGroupOverlap' => ['sometimes', 'boolean'],
            'deliveryAssignments' => ['sometimes', 'array', 'min:1'],
            'deliveryAssignments.*.staffId' => ['required', 'integer', 'min:1'],
            'deliveryAssignments.*.compensationRoleId' => ['required', 'integer', 'min:1'],
            'deliveryAssignments.*.allocationBps' => ['required', 'integer', 'min:1', 'max:10000'],
            'deliveryAssignments.*.isPrimary' => ['sometimes', 'boolean'],
            'assignmentCommandKey' => ['required_with:deliveryAssignments', 'uuid'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('startsAt') && $this->filled('endsAt')
                && strtotime($this->input('endsAt')) <= strtotime($this->input('startsAt'))) {
                $validator->errors()->add('endsAt', 'endsAt must be after startsAt');
            }
        });
    }
}
