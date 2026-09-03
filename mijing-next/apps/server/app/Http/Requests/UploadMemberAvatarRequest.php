<?php

namespace App\Http\Requests;

class UploadMemberAvatarRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
