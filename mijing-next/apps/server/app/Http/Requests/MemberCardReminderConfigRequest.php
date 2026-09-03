<?php

namespace App\Http\Requests;

class MemberCardReminderConfigRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expiringWithinDays' => ['required', 'integer', 'min:1', 'max:365'],
            'zeroBalanceThreshold' => ['sometimes', 'numeric', 'min:0', 'max:999999'],
        ];
    }
}
