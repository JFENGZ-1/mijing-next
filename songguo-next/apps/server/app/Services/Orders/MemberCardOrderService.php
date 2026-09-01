<?php

namespace App\Services\Orders;

use App\Enums\MemberCardOrderStatus;
use App\Enums\OrderAmountCorrectionType;
use App\Models\CardProduct;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\OrderAmountCorrection;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MemberCardOrderService
{
    public function memberOrdersQuery(Staff $staff, Site $site, Member $member): Builder
    {
        return MemberCardOrder::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('member_id', $member->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function memberSelfOrdersQuery(Member $member): Builder
    {
        return MemberCardOrder::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * @return array{order: MemberCardOrder, correctionEntryIds: list<int>, created: bool}
     */
    public function correctAmount(Staff $staff, Site $site, MemberCardOrder $order, array $payload): array
    {
        $commandKey = $payload['commandKey'];

        return DB::transaction(function () use ($staff, $site, $order, $payload, $commandKey) {
            $existing = OrderAmountCorrection::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return [
                    'order' => MemberCardOrder::query()
                        ->where('tenant_id', $staff->tenant_id)
                        ->whereKey($existing->order_id)
                        ->firstOrFail(),
                    'correctionEntryIds' => [$existing->id],
                    'created' => false,
                ];
            }

            $locked = MemberCardOrder::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('site_id', $site->id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($locked->status === MemberCardOrderStatus::Voided, 409, 'ORDER_VOIDED_MUTATION_BLOCKED');

            $correctedAmount = $this->decimalString($payload['amount']);
            $correctionEntryIds = [];

            if (! empty($payload['correctsEntryId'])) {
                $original = OrderAmountCorrection::query()
                    ->where('tenant_id', $staff->tenant_id)
                    ->where('order_id', $locked->id)
                    ->whereKey($payload['correctsEntryId'])
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_if(
                    $original->entry_type === OrderAmountCorrectionType::Reversal,
                    409,
                    'ORDER_CORRECTION_NOT_REVERSIBLE',
                );

                $alreadyReversed = OrderAmountCorrection::query()
                    ->where('tenant_id', $staff->tenant_id)
                    ->where('reversal_of_id', $original->id)
                    ->where('entry_type', OrderAmountCorrectionType::Reversal)
                    ->exists();

                abort_if($alreadyReversed, 409, 'ORDER_CORRECTION_ALREADY_REVERSED');

                $reversal = OrderAmountCorrection::create([
                    'tenant_id' => $staff->tenant_id,
                    'order_id' => $locked->id,
                    'entry_type' => OrderAmountCorrectionType::Reversal,
                    'corrected_amount' => $original->corrected_amount,
                    'reversal_of_id' => $original->id,
                    'reason' => 'Reversal: '.$payload['reason'],
                    'actor_staff_id' => $staff->id,
                    'occurred_at' => now(),
                ]);
                $correctionEntryIds[] = $reversal->id;
            }

            $correction = OrderAmountCorrection::create([
                'tenant_id' => $staff->tenant_id,
                'order_id' => $locked->id,
                'entry_type' => OrderAmountCorrectionType::Correction,
                'corrected_amount' => $correctedAmount,
                'reversal_of_id' => $payload['correctsEntryId'] ?? null,
                'command_key' => $commandKey,
                'reason' => $payload['reason'],
                'actor_staff_id' => $staff->id,
                'occurred_at' => now(),
            ]);
            $correctionEntryIds[] = $correction->id;

            return [
                'order' => $locked->fresh(['amountCorrections']),
                'correctionEntryIds' => $correctionEntryIds,
                'created' => true,
            ];
        });
    }

    /**
     * @return array{order: MemberCardOrder, created: bool}
     */
    public function voidOrder(Staff $staff, Site $site, MemberCardOrder $order, array $payload): array
    {
        $commandKey = $payload['commandKey'];

        return DB::transaction(function () use ($staff, $site, $order, $payload, $commandKey) {
            $locked = MemberCardOrder::query()
                ->where('tenant_id', $staff->tenant_id)
                ->where('site_id', $site->id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === MemberCardOrderStatus::Voided) {
                if (($locked->metadata['voidCommandKey'] ?? null) === $commandKey) {
                    return ['order' => $locked, 'created' => false];
                }

                abort(409, 'ORDER_ALREADY_VOIDED');
            }

            abort_unless(
                $locked->status === MemberCardOrderStatus::PendingPayment,
                409,
                'ORDER_VOID_INVALID',
            );

            $metadata = $locked->metadata ?? [];
            $metadata['voidCommandKey'] = $commandKey;
            $metadata['voidReason'] = $payload['reason'];

            $locked->status = MemberCardOrderStatus::Voided;
            $locked->voided_at = now();
            $locked->metadata = $metadata;
            $locked->save();

            return ['order' => $locked->fresh(), 'created' => true];
        });
    }

    public function effectiveAmount(MemberCardOrder $order): string
    {
        $order->loadMissing('amountCorrections');

        $activeCorrection = $order->amountCorrections
            ->where('entry_type', OrderAmountCorrectionType::Correction)
            ->filter(function (OrderAmountCorrection $entry) use ($order) {
                return ! $order->amountCorrections
                    ->where('entry_type', OrderAmountCorrectionType::Reversal)
                    ->where('reversal_of_id', $entry->id)
                    ->isNotEmpty();
            })
            ->sortByDesc('id')
            ->first();

        return $this->decimalString($activeCorrection?->corrected_amount ?? $order->amount);
    }

    /**
     * @return array<string, mixed>
     */
    public function orderSummary(MemberCardOrder $order): array
    {
        return [
            'id' => $order->id,
            'orderNo' => $order->order_no,
            'memberId' => $order->member_id,
            'memberCardId' => $order->member_card_id,
            'originalAmount' => $this->decimalString($order->amount),
            'effectiveAmount' => $this->effectiveAmount($order),
            'status' => $order->status->value,
            'voidedAt' => $order->voided_at?->toIso8601String(),
            'paymentExpiresAt' => $order->payment_expires_at?->toIso8601String(),
            'closedAt' => $order->closed_at?->toIso8601String(),
            'closeReason' => $order->close_reason,
            'createdAt' => $order->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function memberOrderDetail(MemberCardOrder $order): array
    {
        $order->loadMissing(['amountCorrections', 'memberCard']);
        $metadata = $order->metadata ?? [];
        $productName = null;
        $cardProductId = (int) ($metadata['cardProductId'] ?? 0);

        if ($cardProductId > 0) {
            $productName = CardProduct::query()
                ->where('tenant_id', $order->tenant_id)
                ->whereKey($cardProductId)
                ->value('name');
        }

        if ($productName === null && $order->memberCard) {
            $productName = $order->memberCard->product_snapshot['name'] ?? null;
        }

        $siteName = Site::query()
            ->where('tenant_id', $order->tenant_id)
            ->whereKey($order->site_id)
            ->value('name');

        $detail = [
            ...$this->orderSummary($order),
            'siteId' => $order->site_id,
            'siteName' => $siteName,
            'productName' => $productName,
            'channel' => $metadata['channel'] ?? null,
        ];

        if ($order->memberCard) {
            $detail['memberCard'] = $this->memberCardSummary($order->memberCard);
        }

        return $detail;
    }

    /**
     * @return array<string, mixed>
     */
    private function memberCardSummary(MemberCard $card): array
    {
        $snapshot = $card->product_snapshot ?? [];

        return [
            'id' => $card->id,
            'cardType' => $card->card_type->value,
            'status' => $card->status->value,
            'name' => $snapshot['name'] ?? null,
            'cachedBalance' => $this->nullableDecimal($card->cached_balance),
            'cachedRemainingCount' => $card->cached_remaining_count,
            'validFrom' => $card->valid_from?->toDateString(),
            'validUntil' => $card->valid_until?->toDateString(),
        ];
    }

    private function nullableDecimal(mixed $value): ?string
    {
        return $value === null ? null : $this->decimalString($value);
    }

    private function decimalString(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
