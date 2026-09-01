<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

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
            'paymentMethod' => ['sometimes', Rule::in(['online', 'balance'])],
        ];
    }
}
