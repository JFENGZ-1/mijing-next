<?php

namespace App\Services\Orders;

use App\Enums\MemberCardOrderStatus;
use App\Models\CardProduct;
use App\Models\Member;
use App\Models\MemberCardOrder;
use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;

class MemberCardSaleCategoryService
{
    public const NEW_SALE = 'new';

    public const RENEWAL = 'renewal';

    /**
     * A renewal is a later purchase of the same card product by the same member
     * at the same site. Only a verifiable paid order establishes purchase history.
     */
    public function classify(Member $member, Site $site, CardProduct $product): string
    {
        $priorPaidOrder = MemberCardOrder::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('site_id', $site->id)
            ->where('member_id', $member->id)
            ->where('status', MemberCardOrderStatus::Paid)
            ->where(function (Builder $orders) use ($product) {
                $orders->where('metadata->cardProductId', $product->id)
                    ->orWhereHas('memberCard', fn (Builder $cards) => $cards
                        ->where('card_product_id', $product->id));
            })
            ->select('member_card_orders.id')
            ->lockForUpdate()
            ->first();

        return $priorPaidOrder === null ? self::NEW_SALE : self::RENEWAL;
    }
}
