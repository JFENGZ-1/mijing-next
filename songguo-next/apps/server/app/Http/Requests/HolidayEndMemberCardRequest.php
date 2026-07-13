<?php

namespace App\Http\Requests;

class HolidayEndMemberCardRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'endDate' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ];
    }
}
