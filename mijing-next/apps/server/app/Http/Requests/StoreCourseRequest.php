<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCoursePayload;

class StoreCourseRequest extends ApiFormRequest
{
    use ValidatesCoursePayload;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->courseRules(requireCourseType: true);
    }

    public function withValidator($validator): void
    {
        $this->validateCourseTypeFields($validator);
    }
}
