<?php

namespace App\Http\Requests;

class UpdatePayrollCoachRulesRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'groupCourses' => ['sometimes', 'array'],
            'groupCourses.*.courseId' => ['required', 'integer', 'min:1'],
            'groupCourses.*.unitPriceCents' => ['required', 'integer', 'min:0'],
            'groupCourses.*.additionalPriceCents' => ['nullable', 'integer', 'min:0'],
            'groupCourses.*.supplementalRatePercent' => ['nullable', 'integer', 'min:0', 'max:99'],
            'privateCourses' => ['sometimes', 'array'],
            'privateCourses.*.courseId' => ['required', 'integer', 'min:1'],
            'privateCourses.*.unitPriceCents' => ['required', 'integer', 'min:0'],
            'privateCourses.*.additionalPriceCents' => ['nullable', 'integer', 'min:0'],
            'privateCourses.*.supplementalRatePercent' => ['nullable', 'integer', 'min:0', 'max:99'],
        ];
    }
}
