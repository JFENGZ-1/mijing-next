<?php

namespace App\Http\Requests;

class HolidayStartMemberCardRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'beginDate' => ['nullable', 'date'],
            'plannedEndDate' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ];
    }
}
