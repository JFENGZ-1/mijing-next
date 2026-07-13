<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffDirectoryRequest extends FormRequest
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
            'displayName' => ['required', 'string', 'max:80'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => [Rule::in(['coach', 'sales'])],
            'roleId' => ['required', 'integer', 'min:1'],
        ];
    }
}
