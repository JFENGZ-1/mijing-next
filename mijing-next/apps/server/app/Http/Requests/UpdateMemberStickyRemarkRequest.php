<?php

namespace App\Http\Requests;

class UpdateMemberStickyRemarkRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'stickyRemark' => ['present', 'nullable', 'string', 'max:500'],
        ];
    }
}
