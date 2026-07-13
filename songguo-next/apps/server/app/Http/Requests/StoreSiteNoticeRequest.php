<?php

namespace App\Http\Requests;

class StoreSiteNoticeRequest extends ApiFormRequest
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
            'title' => ['required', 'string', 'max:18'],
            'body' => ['required', 'string', 'max:5000'],
            'displayDays' => ['required', 'integer', 'min:1', 'max:365'],
            'coverImageUrl' => ['sometimes', 'nullable', 'string', 'max:512'],
        ];
    }
}
