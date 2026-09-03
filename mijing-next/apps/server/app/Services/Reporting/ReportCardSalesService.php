<?php

namespace App\Services\Reporting;

use App\Enums\MemberCardOrderStatus;
use App\Models\CardProduct;
use App\Models\MemberCardOrder;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Orders\MemberCardOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportCardSalesService
{
    public function __construct(
        private readonly MemberCardOrderService $orders,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(Staff $staff, Site $site, int $year, int $month): array
    {
        [$start, $end] = $this->monthRange($year, $month);
        $orders = $this->paidOrders($staff, $site, $start, $end);
        $cardProductIds = $this->resolveCardProductIds($orders);

        $products = CardProduct::query()
            ->where('tenant_id', $staff->tenant_id)
            ->whereIn('id', $cardProductIds)
            ->get()
            ->keyBy('id');

        $rows = $orders
            ->groupBy(fn (MemberCardOrder $order) => $this->cardProductIdForOrder($order) ?? 0)
            ->map(function (Collection $productOrders, int $cardProductId) use ($products) {
                $product = $products->get($cardProductId);

                return [
                    'cardProductId' => $cardProductId > 0 ? $cardProductId : null,
                    'cardProductName' => $product?->name ?? ($productOrders->first()?->memberCard?->product_snapshot['name'] ?? '未知卡种'),
                    'salesCount' => $productOrders->count(),
                    'revenue' => $this->decimalString(
                        $productOrders->sum(fn (MemberCardOrder $order) => (float) $this->orders->effectiveAmount($order)),
                    ),
                ];
            })
            ->sort(fn (array $left, array $right) => (float) $right['revenue'] <=> (float) $left['revenue'])
            ->values();

        return [
            'year' => $year,
            'month' => $month,
            'totals' => [
                'cardProductCount' => $rows->count(),
                'salesCount' => $orders->count(),
                'revenue' => $this->decimalString(
                    $orders->sum(fn (MemberCardOrder $order) => (float) $this->orders->effectiveAmount($order)),
                ),
            ],
            'items' => $rows->all(),
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(
        Staff $staff,
        Site $site,
        int $year,
        int $month,
        ?int $cardProductId,
        int $page,
        int $perPage,
    ): array {
        [$start, $end] = $this->monthRange($year, $month);
        $query = $this->paidOrdersQuery($staff, $site, $start, $end);
        $this->applyCardProductFilter($query, $cardProductId);

        $total = (clone $query)->count();
        $revenue = (float) (clone $query)
            ->selectRaw('COALESCE(SUM('.$this->effectiveAmountExpression().'), 0) as aggregate')
            ->value('aggregate');
        $paginator = $query
            ->with(['member.crmProfile', 'amountCorrections'])
            ->orderByPaidAt('desc')
            ->orderByDesc('member_card_orders.id')
            ->paginate($perPage, ['member_card_orders.*'], 'page', $page);
        $canReadMemberNames = $staff->hasPermission('crm.member.read', $site->id);

        return [
            'year' => $year,
            'month' => $month,
            'cardProductId' => $cardProductId,
            'totals' => [
                'salesCount' => $total,
                'revenue' => $this->decimalString($revenue),
            ],
            'items' => collect($paginator->items())
                ->map(fn (MemberCardOrder $order) => [
                    'orderId' => $order->id,
                    'orderNo' => $order->order_no,
                    'memberId' => $order->member_id,
                    'memberName' => $canReadMemberNames
                        ? $order->member?->crmProfile?->name
                        : $this->maskName($order->member?->crmProfile?->name),
                    'amount' => $this->decimalString((float) $this->orders->effectiveAmount($order)),
                    'paidAt' => $order->reportingPaidAt()?->toIso8601String(),
                ])
                ->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * @return Collection<int, MemberCardOrder>
     */
    private function paidOrders(Staff $staff, Site $site, Carbon $start, Carbon $end): Collection
    {
        return $this->paidOrdersQuery($staff, $site, $start, $end)
            ->with(['member.crmProfile', 'memberCard', 'amountCorrections'])
            ->get();
    }

    private function paidOrdersQuery(Staff $staff, Site $site, Carbon $start, Carbon $end): Builder
    {
        return MemberCardOrder::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', MemberCardOrderStatus::Paid)
            ->wherePaidAtBetween($start, $end);
    }

    private function applyCardProductFilter(Builder $query, ?int $cardProductId): void
    {
        if ($cardProductId !== null) {
            $query->where(function (Builder $orders) use ($cardProductId) {
                $orders->where('metadata->cardProductId', $cardProductId)
                    ->orWhere(function (Builder $legacy) use ($cardProductId) {
                        $legacy->whereNull('metadata->cardProductId')
                            ->whereHas('memberCard', fn (Builder $cards) => $cards
                                ->where('card_product_id', $cardProductId));
                    });
            });

            return;
        }

        $query
            ->whereNull('metadata->cardProductId')
            ->where(function (Builder $legacy) {
                $legacy->whereNull('member_card_id')
                    ->orWhereHas('memberCard', fn (Builder $cards) => $cards
                        ->whereNull('card_product_id'));
            });
    }

    private function effectiveAmountExpression(): string
    {
        return <<<'SQL'
            COALESCE(
                (
                    SELECT active_correction.corrected_amount
                    FROM order_amount_corrections AS active_correction
                    WHERE active_correction.tenant_id = member_card_orders.tenant_id
                      AND active_correction.order_id = member_card_orders.id
                      AND active_correction.entry_type = 'correction'
                      AND NOT EXISTS (
                          SELECT 1
                          FROM order_amount_corrections AS reversal
                          WHERE reversal.reversal_of_id = active_correction.id
                            AND reversal.entry_type = 'reversal'
                      )
                    ORDER BY active_correction.id DESC
                    LIMIT 1
                ),
                member_card_orders.amount
            )
            SQL;
    }

    /**
     * @param  Collection<int, MemberCardOrder>  $orders
     * @return list<int>
     */
    private function resolveCardProductIds(Collection $orders): array
    {
        return $orders
            ->map(fn (MemberCardOrder $order) => $this->cardProductIdForOrder($order))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function cardProductIdForOrder(MemberCardOrder $order): ?int
    {
        $fromMetadata = $order->metadata['cardProductId'] ?? null;
        if (is_numeric($fromMetadata)) {
            return (int) $fromMetadata;
        }

        $memberCard = $order->memberCard;
        if ($memberCard?->card_product_id) {
            return (int) $memberCard->card_product_id;
        }

        return null;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthRange(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();

        return [$start, $start->copy()->endOfMonth()];
    }

    private function decimalString(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function maskName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        return mb_substr($name, 0, 1).str_repeat('*', max(mb_strlen($name) - 1, 1));
    }
}
