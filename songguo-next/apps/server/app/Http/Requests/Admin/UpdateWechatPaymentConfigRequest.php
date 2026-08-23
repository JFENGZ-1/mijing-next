<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWechatPaymentConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:0'],
            'enabled' => ['required', 'boolean'],
            'merchantId' => ['sometimes', 'nullable', 'string', 'max:64'],
            'merchantSerialNo' => ['sometimes', 'nullable', 'string', 'max:128'],
            'privateKey' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'apiV3Key' => ['sometimes', 'nullable', 'string', 'size:32'],
            'platformPublicKey' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'platformPublicKeyId' => ['sometimes', 'nullable', 'string', 'max:128'],
            'notifyUrl' => ['sometimes', 'nullable', 'url:https', 'max:500'],
            'webhookSecret' => ['sometimes', 'nullable', 'string', 'min:32', 'max:500'],
            'clearSecrets' => ['sometimes', 'array'],
            'clearSecrets.*' => ['string', 'in:privateKey,apiV3Key,platformPublicKey,webhookSecret'],
        ];
    }
}
