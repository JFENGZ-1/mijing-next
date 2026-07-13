<?php

namespace App\Http\Requests;

class UpdateNotificationChannelConfigRequest extends ApiFormRequest
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
            'channels' => ['sometimes', 'array'],
            'channels.*.key' => ['required_with:channels', 'string', 'max:80'],
            'channels.*.enabled' => ['required_with:channels', 'boolean'],
            'managerStaffIds' => ['sometimes', 'array'],
            'managerStaffIds.*' => ['integer', 'min:1'],
        ];
    }
}
