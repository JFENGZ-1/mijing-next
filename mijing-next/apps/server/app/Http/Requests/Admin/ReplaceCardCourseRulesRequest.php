<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReplaceCardCourseRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rules' => ['present', 'array', 'max:500'],
            'rules.*.courseId' => ['required', 'integer', 'min:1', 'distinct'],
            'rules.*.deductionKind' => ['required', Rule::in(['amount', 'count', 'period_auto'])],
            'rules.*.deductionAmountCents' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'rules.*.deductionCount' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'rules.*.effectiveAt' => ['sometimes', 'nullable', 'date'],
            'expectedVersion' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:2', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ];
    }
}
