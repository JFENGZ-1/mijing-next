<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffDirectoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'displayName' => ['sometimes', 'string', 'max:80'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'capabilities' => ['sometimes', 'array'],
            'capabilities.*' => [Rule::in(['coach', 'sales'])],
            'roleId' => ['sometimes', 'integer', 'min:1'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
