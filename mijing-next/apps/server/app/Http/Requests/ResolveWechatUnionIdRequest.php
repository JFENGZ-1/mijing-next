<?php

namespace App\Http\Requests;

class ResolveWechatUnionIdRequest extends ApiFormRequest
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
            'code' => ['required', 'string', 'min:1', 'max:128'],
        ];
    }
}
