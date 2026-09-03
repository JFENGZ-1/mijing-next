<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\UpdateCardProductRequest;

class UpdateAdminCardProductRequest extends UpdateCardProductRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'commandKey' => ['required', 'uuid'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
