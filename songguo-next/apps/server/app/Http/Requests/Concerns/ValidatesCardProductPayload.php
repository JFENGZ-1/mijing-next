<?php

namespace App\Http\Requests\Concerns;

use App\Enums\CardProductCourseScopeKind;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

trait ValidatesCardProductPayload
{
    protected function cardProductRules(bool $requireCardType): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'faceValue' => ['nullable', 'numeric', 'min:0'],
            'initialCount' => ['nullable', 'integer', 'min:1'],
            'validityDays' => ['nullable', 'integer', 'min:1'],
            'validityMode' => ['nullable', 'string', 'max:32'],
            'activationMode' => ['nullable', 'string', 'max:32'],
            'saleStatus' => ['nullable', Rule::enum(CardProductSaleStatus::class)],
            'sortOrder' => ['nullable', 'integer', 'min:0'],
            'scopeConfig' => ['nullable', 'array'],
            'bookingRules' => ['nullable', 'array'],
            'courseScopes' => ['nullable', 'array'],
            'courseScopes.*.scopeKind' => ['required', Rule::enum(CardProductCourseScopeKind::class)],
            'courseScopes.*.scopeKey' => ['required', 'string', 'max:80'],
            'courseScopes.*.displayName' => ['nullable', 'string', 'max:120'],
            'courseScopes.*.priceOverride' => ['nullable', 'numeric', 'min:0'],
            'courseScopes.*.sortOrder' => ['nullable', 'integer', 'min:0'],
        ];

        if ($requireCardType) {
            $rules['cardType'] = ['required', Rule::enum(CardType::class)];
        } else {
            $rules['cardType'] = ['sometimes', Rule::enum(CardType::class)];
        }

        return $rules;
    }

    protected function validateCardTypeFields(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $cardType = $this->input('cardType');
            if (! $cardType) {
                return;
            }

            if ($cardType === CardType::StoredValue->value && ! $this->filled('faceValue')) {
                $validator->errors()->add('faceValue', '储值卡必须填写面值');
            }

            if ($cardType === CardType::Count->value && ! $this->filled('initialCount')) {
                $validator->errors()->add('initialCount', '计次卡必须填写初始次数');
            }

            if ($cardType === CardType::Period->value && ! $this->filled('validityDays')) {
                $validator->errors()->add('validityDays', '期限卡必须填写有效天数');
            }
        });
    }
}
