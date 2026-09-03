<?php

namespace App\Http\Requests;

class UpdateMemberCarouselRequest extends ApiFormRequest
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
            'items' => ['present', 'array', 'max:5'],
            'items.*.imageUrl' => ['required', 'string', 'url', 'max:512'],
            'items.*.linkUrl' => ['sometimes', 'nullable', 'string', 'url', 'max:512'],
            'items.*.sortOrder' => ['sometimes', 'integer', 'min:0'],
            'defaultImageUrl' => ['sometimes', 'nullable', 'string', 'url', 'max:512'],
        ];
    }
}
