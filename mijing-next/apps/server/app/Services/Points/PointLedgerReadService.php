<?php

namespace App\Services\Points;

use App\Enums\PointLedgerDirection;
use App\Models\Account;
use App\Models\Member;
use App\Models\MemberPointBalance;
use App\Models\PointLedgerEntry;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class PointLedgerReadService
{
    public function assertEnabled(Tenant $tenant): void
    {
        abort_unless($tenant->points_enabled, 404);
    }

    public function ledgerQuery(Member $member): Builder
    {
        return PointLedgerEntry::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function totalPoint(Member $member): int
    {
        return (int) (MemberPointBalance::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->value('balance') ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function memberLedger(Account $account, Member $member): array
    {
        $member->loadMissing('tenant', 'account.memberProfile', 'crmProfile');
        $profile = $account->memberProfile;

        return [
            'displayName' => $profile?->display_name ?? $member->crmProfile?->name,
            'avatarObjectKey' => $profile?->avatar_object_key,
            'totalPoint' => $this->totalPoint($member),
            'descriptionText' => $member->tenant?->points_description_text,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ledgerEntry(PointLedgerEntry $entry): array
    {
        $signedAmount = $entry->direction === PointLedgerDirection::Credit
            ? $entry->amount_delta
            : -$entry->amount_delta;

        return [
            'id' => $entry->id,
            'title' => $entry->reason,
            'amountDelta' => $signedAmount,
            'direction' => $entry->direction->value,
            'reason' => $entry->reason,
            'actorStaffId' => $entry->actor_staff_id,
            'createdAt' => $entry->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ledgerEntries(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->items())
            ->map(fn (PointLedgerEntry $entry) => $this->ledgerEntry($entry))
            ->values()
            ->all();
    }
}
