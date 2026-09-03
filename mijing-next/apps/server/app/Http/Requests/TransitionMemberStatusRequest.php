<?php

namespace App\Http\Requests;

class TransitionMemberStatusRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'targetStatus' => ['required', 'string', 'in:active,frozen,closed'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
