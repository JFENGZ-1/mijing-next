<?php

namespace App\Http\Requests;

class UpsertStaffRoleRequest extends ApiFormRequest
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
            'id' => ['sometimes', 'integer', 'min:1'],
            'name' => ['required', 'string', 'min:1', 'max:80'],
            'permissionIds' => ['required', 'array', 'min:1'],
            'permissionIds.*' => ['integer', 'min:1'],
        ];
    }
}
