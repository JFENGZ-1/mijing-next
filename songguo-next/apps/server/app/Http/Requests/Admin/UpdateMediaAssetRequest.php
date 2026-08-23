<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'tenantId' => ['sometimes', 'nullable', 'integer', 'exists:tenants,id'],
            'title' => ['sometimes', 'nullable', 'string', 'max:160'],
            'altText' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
