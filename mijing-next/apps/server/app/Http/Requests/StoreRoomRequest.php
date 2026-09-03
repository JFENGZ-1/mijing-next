<?php

namespace App\Http\Requests;

class StoreRoomRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'sortOrder' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
