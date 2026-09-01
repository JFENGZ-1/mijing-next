<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\StoreCardProductRequest;

class StoreAdminCardProductRequest extends StoreCardProductRequest
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
