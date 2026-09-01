<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCompensationRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'roleType' => ['required', Rule::in(['delivery', 'share'])],
            'version' => [$this->isMethod('POST') ? 'sometimes' : 'required', 'integer', $this->isMethod('POST') ? 'min:0' : 'min:1'],
            'reason' => ['sometimes', 'nullable', 'string', 'min:2', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ];
    }
}
