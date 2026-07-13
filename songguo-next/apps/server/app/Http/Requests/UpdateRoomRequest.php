<?php

namespace App\Http\Requests;

class UpdateRoomRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:80'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'sortOrder' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
