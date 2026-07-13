<?php

namespace App\Http\Requests;

class StaffMemberLinkDecisionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'decision' => ['required', 'string', 'in:approve_link,approve_separate,reject'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
