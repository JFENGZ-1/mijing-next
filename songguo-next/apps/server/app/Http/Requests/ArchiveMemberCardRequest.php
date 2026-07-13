<?php

namespace App\Http\Requests;

class ArchiveMemberCardRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ];
    }
}
