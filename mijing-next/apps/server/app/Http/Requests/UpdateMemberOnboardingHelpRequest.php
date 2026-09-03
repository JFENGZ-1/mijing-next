<?php

namespace App\Http\Requests;

class UpdateMemberOnboardingHelpRequest extends ApiFormRequest
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
            'posterUrl' => ['sometimes', 'nullable', 'string', 'url', 'max:512'],
            'stepUrl' => ['sometimes', 'nullable', 'string', 'url', 'max:512'],
        ];
    }
}
