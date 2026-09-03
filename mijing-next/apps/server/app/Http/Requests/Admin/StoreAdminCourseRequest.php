<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\StoreCourseRequest;

class StoreAdminCourseRequest extends StoreCourseRequest
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
