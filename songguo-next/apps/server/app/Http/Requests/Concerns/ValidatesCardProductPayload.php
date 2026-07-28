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
            // 卡级预约规则（对标原版会员卡「高级选项」）
            'bookingRules' => ['nullable', 'array'],
            'bookingRules.defaultPrice' => ['nullable', 'numeric', 'min:0'],
            'bookingRules.timeRanges' => ['nullable', 'array'],
            'bookingRules.timeRanges.*.start' => ['required_with:bookingRules.timeRanges', 'date_format:H:i'],
            'bookingRules.timeRanges.*.end' => ['required_with:bookingRules.timeRanges', 'date_format:H:i'],
            'bookingRules.bookingLimit' => ['nullable', 'array'],
            'bookingRules.bookingLimit.perDay' => ['nullable', 'integer', 'min:1'],
            'bookingRules.bookingLimit.perWeek' => ['nullable', 'integer', 'min:1'],
            'bookingRules.bookingLimit.perMonth' => ['nullable', 'integer', 'min:1'],
            'bookingRules.advanceLimit' => ['nullable', 'integer', 'min:1'],
            'bookingRules.cancelLimit' => ['nullable', 'array'],
            'bookingRules.cancelLimit.perDay' => ['nullable', 'integer', 'min:0'],
            'bookingRules.cancelLimit.perWeek' => ['nullable', 'integer', 'min:0'],
            'bookingRules.cancelLimit.perMonth' => ['nullable', 'integer', 'min:0'],
            'bookingRules.repeatBooking' => ['nullable', 'array'],
            'bookingRules.repeatBooking.mode' => ['nullable', Rule::in(['deny', 'limit', 'allow'])],
            'bookingRules.repeatBooking.max' => ['nullable', 'integer', 'min:1'],
            'bookingRules.activationDays' => ['nullable', 'integer', 'min:1'],
            'bookingRules.multiPerson' => ['nullable', 'array'],
            'bookingRules.multiPerson.mode' => ['nullable', Rule::in(['self', 'unlimited', 'limited'])],
            'bookingRules.multiPerson.enabled' => ['nullable', 'boolean'],
            'bookingRules.multiPerson.max' => ['nullable', 'integer', 'min:2'],
            // 旷课处罚（对标原版：周/月双窗口 + 三动作，动作1对钱卡为不退课费、期限卡为仅标记）
            'bookingRules.absencePenalty' => ['nullable', 'array'],
            'bookingRules.absencePenalty.weekThreshold' => ['nullable', 'integer', 'min:1'],
            'bookingRules.absencePenalty.monthThreshold' => ['nullable', 'integer', 'min:1'],
            'bookingRules.absencePenalty.action' => ['nullable', Rule::in(['mark', 'no_refund', 'mark_or_no_refund', 'forbid', 'deduct'])],
            'bookingRules.absencePenalty.forbidDays' => ['nullable', 'integer', 'min:1'],
            'bookingRules.absencePenalty.deductValue' => ['nullable', 'numeric', 'min:0.01'],
            // 兼容旧结构
            'bookingRules.absencePenalty.window' => ['nullable', Rule::in(['week', 'month'])],
            'bookingRules.absencePenalty.threshold' => ['nullable', 'integer', 'min:1'],
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
