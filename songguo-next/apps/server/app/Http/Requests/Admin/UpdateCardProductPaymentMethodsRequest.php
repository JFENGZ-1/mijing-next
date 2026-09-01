<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCardProductPaymentMethodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allowedPaymentMethods' => ['required', 'array', 'min:1', 'max:2'],
            'allowedPaymentMethods.*' => ['required', 'distinct', Rule::in(['online', 'balance'])],
            'version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:2', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ];
    }
}
