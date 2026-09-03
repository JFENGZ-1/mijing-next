<?php

namespace App\Http\Requests;

class MemberLinkDecisionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'decision' => ['required', 'string', 'in:link,not_me'],
        ];
    }
}
