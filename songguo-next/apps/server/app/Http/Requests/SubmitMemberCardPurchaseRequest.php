<?php

namespace App\Http\Requests;

class SubmitMemberCardPurchaseRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cardProductId' => ['required', 'integer', 'min:1'],
            'commandKey' => ['required', 'uuid'],
        ];
    }
}
