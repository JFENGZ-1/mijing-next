<?php

namespace App\Http\Requests;

use App\Enums\PayrollSalesMode;
use Illuminate\Validation\Rule;

class UpdatePayrollSalesConfigRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'mode' => ['nullable', 'string', Rule::in(array_column(PayrollSalesMode::cases(), 'value'))],
            'settings' => ['sometimes', 'array'],
            'settings.newSaleRatePercent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'settings.renewalRatePercent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'settings.newSaleTiers' => ['sometimes', 'array'],
            'settings.newSaleTiers.*.fromAmountCents' => ['required', 'integer', 'min:0'],
            'settings.newSaleTiers.*.toAmountCents' => ['nullable', 'integer', 'min:0'],
            'settings.newSaleTiers.*.ratePercent' => ['required', 'integer', 'min:0', 'max:100'],
            'settings.renewalTiers' => ['sometimes', 'array'],
            'settings.renewalTiers.*.fromAmountCents' => ['required', 'integer', 'min:0'],
            'settings.renewalTiers.*.toAmountCents' => ['nullable', 'integer', 'min:0'],
            'settings.renewalTiers.*.ratePercent' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}
