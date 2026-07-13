<?php

namespace App\Services\Cards;

use App\Models\MemberCard;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class MemberCardBatchService
{
    public function __construct(
        private readonly MemberCardReadService $reader,
        private readonly MemberCardAdjustService $adjuster,
        private readonly MemberCardStateService $state,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function batchBalanceAdjustments(Staff $staff, Site $site, array $payload): array
    {
        return $this->runBatch($staff, $site, $payload, function (MemberCard $card, array $item) use ($staff, $site) {
            return $this->adjuster->adjustBalance($staff, $site, $card, [
                'commandKey' => $item['commandKey'],
                'direction' => $item['direction'],
                'amount' => $item['amount'],
                'reason' => $item['reason'] ?? null,
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function batchValidityExtensions(Staff $staff, Site $site, array $payload): array
    {
        return $this->runBatch($staff, $site, $payload, function (MemberCard $card, array $item) use ($staff, $site) {
            return $this->state->extendValidity($staff, $site, $card, [
                'commandKey' => $item['commandKey'],
                'validUntil' => $item['validUntil'],
                'reason' => $item['reason'] ?? null,
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function batchFreeze(Staff $staff, Site $site, array $payload): array
    {
        return $this->runBatch($staff, $site, $payload, function (MemberCard $card, array $item) use ($staff, $site) {
            return $this->state->freeze($staff, $site, $card, [
                'commandKey' => $item['commandKey'],
                'reason' => $item['reason'] ?? '批量停卡',
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function batchUnfreeze(Staff $staff, Site $site, array $payload): array
    {
        return $this->runBatch($staff, $site, $payload, function (MemberCard $card, array $item) use ($staff, $site) {
            return $this->state->unfreeze($staff, $site, $card, [
                'commandKey' => $item['commandKey'],
                'reason' => $item['reason'] ?? '批量解冻',
            ]);
        });
    }

    /**
     * @param  callable(MemberCard, array<string, mixed>): array<string, mixed>  $handler
     * @return array<string, mixed>
     */
    private function runBatch(Staff $staff, Site $site, array $payload, callable $handler): array
    {
        $succeeded = [];
        $failed = [];

        DB::transaction(function () use ($staff, $site, $payload, $handler, &$succeeded, &$failed) {
            foreach ($payload['items'] as $item) {
                try {
                    $card = $this->reader->staffCard($staff, $site, (int) $item['memberCardId']);
                    $result = $handler($card, $item);
                    $succeeded[] = [
                        'memberCardId' => $card->id,
                        'ledgerEntryIds' => $result['ledgerEntryIds'] ?? [],
                        'created' => $result['created'] ?? true,
                    ];
                } catch (\Throwable $exception) {
                    $failed[] = [
                        'memberCardId' => $item['memberCardId'] ?? null,
                        'code' => $exception->getMessage(),
                    ];
                }
            }
        });

        return [
            'commandKey' => $payload['commandKey'],
            'succeeded' => $succeeded,
            'failed' => $failed,
        ];
    }
}
