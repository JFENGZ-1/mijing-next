<?php

namespace App\Services\Points;

use App\Enums\PointLedgerDirection;
use App\Models\Member;
use App\Models\MemberPointBalance;
use App\Models\PointLedgerEntry;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class PointLedgerWriteService
{
    /**
     * @return array{entry: PointLedgerEntry, balance: int, created: bool}
     */
    public function adjust(Staff $staff, Member $member, array $payload): array
    {
        abort_if($member->tenant_id !== $staff->tenant_id, 404);
        abort_if($member->app_access_status === 'blocked', 403, 'MEMBER_APP_ACCESS_BLOCKED');

        $commandKey = $payload['commandKey'];
        $direction = PointLedgerDirection::from($payload['direction']);
        $amount = (int) $payload['amount'];
        abort_if($amount <= 0, 422, 'POINT_ADJUST_INVALID');

        return DB::transaction(function () use ($staff, $member, $payload, $commandKey, $direction, $amount) {
            $existing = PointLedgerEntry::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return [
                    'entry' => $existing,
                    'balance' => $this->balanceFor($member),
                    'created' => false,
                ];
            }

            $balance = MemberPointBalance::query()
                ->where('tenant_id', $member->tenant_id)
                ->where('member_id', $member->id)
                ->lockForUpdate()
                ->first();

            $currentBalance = $balance?->balance ?? 0;
            $signedDelta = $direction === PointLedgerDirection::Credit ? $amount : -$amount;

            if ($signedDelta < 0 && $currentBalance + $signedDelta < 0) {
                abort(422, 'INSUFFICIENT_POINTS');
            }

            $entry = PointLedgerEntry::create([
                'tenant_id' => $member->tenant_id,
                'member_id' => $member->id,
                'amount_delta' => $amount,
                'direction' => $direction,
                'reason' => $payload['reason'],
                'command_key' => $commandKey,
                'actor_staff_id' => $staff->id,
            ]);

            $newBalance = $currentBalance + $signedDelta;
            if ($balance) {
                $balance->update(['balance' => $newBalance]);
            } else {
                MemberPointBalance::create([
                    'tenant_id' => $member->tenant_id,
                    'member_id' => $member->id,
                    'balance' => $newBalance,
                ]);
            }

            return [
                'entry' => $entry,
                'balance' => $newBalance,
                'created' => true,
            ];
        });
    }

    public function balanceFor(Member $member): int
    {
        return (int) (MemberPointBalance::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->value('balance') ?? 0);
    }
}
