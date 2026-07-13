<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCardProductPayload;

class UpdateCardProductRequest extends ApiFormRequest
{
    use ValidatesCardProductPayload;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            ...$this->cardProductRules(requireCardType: false),
        ];
    }

    public function withValidator($validator): void
    {
        $this->validateCardTypeFields($validator);
    }
}
