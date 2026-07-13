<?php

namespace App\Services\Reporting;

use App\Enums\MemberCardOrderStatus;
use App\Models\CardProduct;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Orders\MemberCardOrderService;
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
    $orders = $this->paidOrders($staff, $site, $start, $end)
      ->filter(fn (MemberCardOrder $order) => $this->cardProductIdForOrder($order) === $cardProductId);

    $total = $orders->count();
    $lastPage = max((int) ceil($total / $perPage), 1);
    $slice = $orders
      ->sortByDesc('created_at')
      ->slice(($page - 1) * $perPage, $perPage)
      ->values();

    return [
      'year' => $year,
      'month' => $month,
      'cardProductId' => $cardProductId,
      'totals' => [
        'salesCount' => $total,
        'revenue' => $this->decimalString(
          $orders->sum(fn (MemberCardOrder $order) => (float) $this->orders->effectiveAmount($order)),
        ),
      ],
      'items' => $slice->map(fn (MemberCardOrder $order) => [
        'orderId' => $order->id,
        'orderNo' => $order->order_no,
        'memberId' => $order->member_id,
        'memberName' => $order->member?->crmProfile?->name,
        'amount' => $this->decimalString((float) $this->orders->effectiveAmount($order)),
        'paidAt' => $order->created_at?->toIso8601String(),
      ])->all(),
      'pagination' => [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'lastPage' => $lastPage,
      ],
      'asOf' => now()->toIso8601String(),
    ];
  }

  /**
   * @return Collection<int, MemberCardOrder>
   */
  private function paidOrders(Staff $staff, Site $site, Carbon $start, Carbon $end): Collection
  {
    return MemberCardOrder::query()
      ->where('tenant_id', $staff->tenant_id)
      ->where('site_id', $site->id)
      ->where('status', MemberCardOrderStatus::Paid)
      ->whereBetween('created_at', [$start, $end])
      ->with(['member.crmProfile', 'memberCard', 'amountCorrections'])
      ->get();
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
}
