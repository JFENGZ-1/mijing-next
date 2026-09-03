<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveCourseCompensationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'courseId' => ['required', 'integer', 'min:1'],
            'sessionFeeCents' => ['required', 'integer', 'min:0'],
            'roleRates' => ['present', 'array', 'max:100'],
            'roleRates.*.compensationRoleId' => ['required', 'integer', 'min:1', 'distinct'],
            'roleRates.*.rateBps' => ['required', 'integer', 'min:0', 'max:10000'],
            'version' => ['required', 'integer', 'min:0'],
            'effectiveAt' => ['sometimes', 'nullable', 'date'],
            'reason' => ['required', 'string', 'min:2', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ];
    }
}
