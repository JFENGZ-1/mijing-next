<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateMemberMiniappLayoutRequest extends ApiFormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.key' => [
                'required',
                'string',
                Rule::in([
                    'showBuyCardBtn', 'showBuyCardPrice', 'showPrivateDrainer', 'privateShow',
                    'showPhoneOfDrainer', 'teamShow', 'showTimeoutTeamPlan', 'showMonthRank',
                    'refuseUserNoLogin', 'refuseUserZeroBalance', 'refuseUserCardExpired', 'refuseUserFocus',
                ]),
            ],
            'items.*.enabled' => ['required', 'boolean'],
        ];
    }
}
