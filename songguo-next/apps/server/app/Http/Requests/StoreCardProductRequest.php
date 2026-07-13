<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCardProductPayload;

class StoreCardProductRequest extends ApiFormRequest
{
    use ValidatesCardProductPayload;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->cardProductRules(requireCardType: true);
    }

    public function withValidator($validator): void
    {
        $this->validateCardTypeFields($validator);
    }
}
