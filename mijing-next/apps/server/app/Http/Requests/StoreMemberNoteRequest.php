<?php

namespace App\Http\Requests;

class StoreMemberNoteRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'correctionOfId' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
