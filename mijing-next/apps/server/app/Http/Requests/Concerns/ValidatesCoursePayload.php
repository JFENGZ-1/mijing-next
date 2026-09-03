<?php

namespace App\Http\Requests\Concerns;

use App\Enums\CourseType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

trait ValidatesCoursePayload
{
    protected function courseRules(bool $requireCourseType): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'durationMinutes' => ['required', 'integer', 'min:1', 'max:600'],
            'difficulty' => ['nullable', 'integer', 'min:1', 'max:5'],
            'minCapacity' => ['nullable', 'integer', 'min:1'],
            'maxCapacity' => ['nullable', 'integer', 'min:1'],
            'defaultRoomId' => ['nullable', 'integer', 'min:1'],
            'coachStaffId' => ['nullable', 'integer', 'min:1'],
            'tags' => ['nullable', 'array'],
            'faceStyle' => ['nullable', 'integer', 'min:0', 'max:99'],
            'displayColor' => ['nullable', 'string', 'max:24'],
            'tags.*' => ['string', 'max:40'],
            'sortOrder' => ['nullable', 'integer', 'min:0'],
        ];

        if ($requireCourseType) {
            $rules['courseType'] = ['required', Rule::enum(CourseType::class)];
        } else {
            $rules['courseType'] = ['sometimes', Rule::enum(CourseType::class)];
        }

        return $rules;
    }

    protected function validateCourseTypeFields(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $courseType = $this->input('courseType');
            if (! $courseType) {
                return;
            }

            if ($courseType === CourseType::Group->value) {
                if (! $this->filled('maxCapacity')) {
                    $validator->errors()->add('maxCapacity', '团课必须填写最大容量');
                }
                if ($this->filled('minCapacity') && $this->filled('maxCapacity')
                    && (int) $this->input('minCapacity') > (int) $this->input('maxCapacity')) {
                    $validator->errors()->add('minCapacity', '最小容量不能大于最大容量');
                }
            }

            if ($courseType === CourseType::Private->value && ! $this->filled('coachStaffId')) {
                $validator->errors()->add('coachStaffId', '私教课必须指定教练');
            }
        });
    }
}
