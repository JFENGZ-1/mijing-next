<?php

namespace App\Http\Requests;

class SyncMemberTagsRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'tagIds' => ['required', 'array', 'max:20'],
            'tagIds.*' => ['integer', 'distinct', 'min:1'],
        ];
    }
}
