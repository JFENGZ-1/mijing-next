<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;

class UpsertBookingPolicyRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:0'],
            'group' => ['required', 'array'],
            'group.signMinutesBeforeStart' => ['required', 'integer', 'min:0'],
            'group.autoCheckInMinutesAfterEnd' => ['required', 'integer', 'min:1', 'max:60'],
            'group.advanceBookingDays' => ['required', 'integer', 'min:0'],
            'group.advanceBookingDailyCutoffHour' => ['required', 'integer', 'min:0', 'max:23'],
            'group.advanceBookingDailyCutoffMinute' => ['required', 'integer', 'min:0', 'max:59'],
            'group.bookingCutoffMinutesBeforeStart' => ['required', 'integer', 'min:0'],
            'group.cancelCutoffMinutesBeforeStart' => ['required', 'integer', 'min:0'],
            'group.waitlistEnabled' => ['required', 'boolean'],
            'group.showBookedCount' => ['required', 'boolean'],
            'group.autoCancelUnderMinStudentsEnabled' => ['required', 'boolean'],
            'group.autoCancelUnderMinStudentsMinutesBeforeStart' => ['required', 'integer', 'min:0', 'max:180'],
            'group.calendarDisplayDays' => ['required', 'integer', 'min:1'],
            'group.absentPenaltyEnabled' => ['required', 'boolean'],
            'group.maxBookingsPerDay' => ['nullable', 'integer', 'min:1'],
            'private' => ['required', 'array'],
            'private.advanceBookingDays' => ['required', 'integer', 'min:0'],
            'private.minimumLeadMinutes' => ['required', 'integer', 'min:0'],
            'private.cancelCutoffMinutesBeforeStart' => ['required', 'integer', 'min:0'],
            'private.slotIntervalMinutes' => ['required', 'integer', 'min:5'],
            'private.preparationMinutes' => ['required', 'integer', 'min:0'],
            'private.grayOutBookedSlots' => ['required', 'boolean'],
            'private.groupConflictMode' => ['required', 'string', 'in:block,allow,overlap_warn'],
            'private.absentPenaltyEnabled' => ['required', 'boolean'],
            'private.maxBookingsPerDay' => ['nullable', 'integer', 'min:1'],
            'rules' => ['sometimes', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $group = $this->input('group', []);
            $private = $this->input('private', []);

            if (
                isset($group['cancelCutoffMinutesBeforeStart'], $group['bookingCutoffMinutesBeforeStart'])
                && (int) $group['cancelCutoffMinutesBeforeStart'] < (int) $group['bookingCutoffMinutesBeforeStart']
            ) {
                $validator->errors()->add(
                    'group.cancelCutoffMinutesBeforeStart',
                    '取消预约截止分钟数不能早于停止预约分钟数',
                );
            }

            if (
                isset($private['cancelCutoffMinutesBeforeStart'], $private['minimumLeadMinutes'])
                && (int) $private['cancelCutoffMinutesBeforeStart'] < (int) $private['minimumLeadMinutes']
            ) {
                $validator->errors()->add(
                    'private.cancelCutoffMinutesBeforeStart',
                    '取消预约截止分钟数不能早于最少提前预约分钟数',
                );
            }
        });
    }
}
