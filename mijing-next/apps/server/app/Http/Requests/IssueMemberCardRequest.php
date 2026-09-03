<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class IssueMemberCardRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cardProductId' => ['required', 'integer', 'min:1'],
            'commandKey' => ['required', 'uuid'],
            'openingBalance' => ['nullable', 'numeric', 'min:0'],
            'openingCount' => ['nullable', 'integer', 'min:1'],
            'openingType' => ['nullable', Rule::in([
                'new', 'legacy',
                'immediate', 'first_use', 'first-use',
                'first_class', 'first-class',
                'keep_pending', 'manual', 'delayed',
            ])],
            'reason' => ['required_with:paymentMethod', 'nullable', 'string', 'min:4', 'max:500'],
            'paymentMethod' => ['required_with:actualAmount,paidAmountCents', 'nullable', Rule::in(['online', 'balance'])],
            'actualAmount' => ['nullable', 'string', 'regex:/^\\d{1,10}(?:\\.\\d{1,2})?$/'],
            'paidAmountCents' => ['nullable', 'integer', 'min:0'],
            'shareAssignments' => ['nullable', 'array'],
            'shareAssignments.*.staffId' => ['required', 'integer', 'min:1'],
            'shareAssignments.*.compensationRoleId' => ['required', 'integer', 'min:1'],
            'shareAssignments.*.allocationBps' => ['nullable', 'integer', 'between:0,10000'],
            'shareAssignments.*.effectiveFrom' => ['nullable', 'date_format:Y-m-d'],
            'shareAssignments.*.effectiveUntil' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (($this->filled('actualAmount') || $this->filled('paidAmountCents'))
                && ! $this->filled('paymentMethod')) {
                $validator->errors()->add('paymentMethod', '填写实付金额时必须选择支付方式');
            }
            if ($this->filled('paymentMethod') && ! $this->filled('reason')) {
                $validator->errors()->add('reason', '人工确认收款必须填写原因');
            }
            if ($this->filled('openingBalance') && $this->filled('openingCount')) {
                $validator->errors()->add('openingBalance', 'openingBalance 与 openingCount 不能同时提供');
            }
            if ($this->filled('actualAmount') && $this->filled('paidAmountCents')) {
                $actualCents = \App\Support\Finance\Money::decimalToCents($this->input('actualAmount'));
                if ($actualCents !== (int) $this->input('paidAmountCents')) {
                    $validator->errors()->add('actualAmount', 'actualAmount 与 paidAmountCents 不一致');
                }
            }
        });
    }
}
