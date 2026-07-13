<?php

namespace App\Http\Requests;

class UpdateSiteNoticeRequest extends ApiFormRequest
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
            'title' => ['sometimes', 'string', 'max:18'],
            'body' => ['sometimes', 'string', 'max:5000'],
            'displayDays' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'coverImageUrl' => ['sometimes', 'nullable', 'string', 'max:512'],
        ];
    }
}
