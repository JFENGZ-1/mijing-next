<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.config('admin.media_max_kilobytes'),
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime',
            ],
            'tenantId' => ['sometimes', 'nullable', 'integer', 'exists:tenants,id'],
            'title' => ['sometimes', 'nullable', 'string', 'max:160'],
            'altText' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
