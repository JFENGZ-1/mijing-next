<?php

namespace App\Http\Requests;

class BatchImportStaffMembersRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lines' => ['sometimes', 'array', 'max:500'],
            'lines.*.name' => ['required_with:lines', 'string', 'max:80'],
            'lines.*.mobile' => ['required_with:lines', 'string', 'max:24', 'regex:/^\+?[0-9 -]{7,24}$/'],
            'text' => ['sometimes', 'string', 'max:50000'],
            'assignToMe' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->has('lines') && ! $this->filled('text')) {
                $validator->errors()->add('lines', '请提供 lines 或 text');
            }
        });
    }
}
