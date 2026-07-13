<?php

namespace App\Http\Requests;

class UpdateMembershipAgreementRequest extends ApiFormRequest
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
            'html' => ['required', 'string', 'max:50000'],
        ];
    }
}
