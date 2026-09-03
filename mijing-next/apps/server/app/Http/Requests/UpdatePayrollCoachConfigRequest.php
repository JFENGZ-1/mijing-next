<?php

namespace App\Http\Requests;

use App\Enums\PayrollCoachMode;
use Illuminate\Validation\Rule;

class UpdatePayrollCoachConfigRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'mode' => ['nullable', 'string', Rule::in(array_column(PayrollCoachMode::cases(), 'value'))],
        ];
    }
}
