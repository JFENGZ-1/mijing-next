<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\UpdateCourseRequest;

class UpdateAdminCourseRequest extends UpdateCourseRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'commandKey' => ['required', 'uuid'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
