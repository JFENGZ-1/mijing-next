<?php

namespace App\Http\Requests;

class WechatLoginRequest extends ApiFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'appType' => ['required', 'string', 'in:member,staff'],
            'code' => ['required', 'string', 'min:1', 'max:128', 'not_in:test'],
            'deviceName' => ['nullable', 'string', 'max:120'],
            'tenantId' => ['sometimes', 'integer', 'min:1'],
            'inviteSign' => ['sometimes', 'string', 'min:10', 'max:512'],
        ];
    }
}
