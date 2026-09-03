<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCoursePayload;

class UpdateCourseRequest extends ApiFormRequest
{
    use ValidatesCoursePayload;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            ...$this->courseRules(requireCourseType: false),
        ];
    }

    public function withValidator($validator): void
    {
        $this->validateCourseTypeFields($validator);
    }
}
