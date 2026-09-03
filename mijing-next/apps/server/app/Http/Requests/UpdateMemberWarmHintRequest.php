<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateMemberWarmHintRequest extends ApiFormRequest
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
            'courseType' => ['required', 'integer', Rule::in([6, 7])],
            'title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'text' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
}
