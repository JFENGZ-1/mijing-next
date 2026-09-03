<?php

namespace App\Services\Cards;

use App\Models\CardProduct;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Collection;

class CardProductCrossSiteService
{
  /**
   * @return array{items: list<array<string, mixed>>}
   */
  public function listForSite(Staff $staff, Site $site): array
  {
    $sites = Site::query()
      ->where('tenant_id', $staff->tenant_id)
      ->where('status', 'active')
      ->orderBy('name')
      ->get(['id', 'name', 'code']);

    $products = CardProduct::query()
      ->where('tenant_id', $staff->tenant_id)
      ->where('site_id', $site->id)
      ->whereNull('archived_at')
      ->orderBy('sort_order')
      ->orderBy('id')
      ->get();

    return [
      'items' => $products->map(fn (CardProduct $product) => [
        'cardProductId' => $product->id,
        'name' => $product->name,
        'cardType' => $product->card_type->value,
        'price' => number_format((float) $product->price, 2, '.', ''),
        'linkedSiteIds' => $this->linkedSiteIds($product),
        'sites' => $sites->map(fn (Site $entry) => [
          'siteId' => $entry->id,
          'siteName' => $entry->name,
          'linked' => in_array($entry->id, $this->linkedSiteIds($product), true),
        ])->all(),
      ])->all(),
    ];
  }

  /**
   * @return array<string, mixed>
   */
  public function updateLink(Staff $staff, Site $site, CardProduct $product, array $payload): array
  {
    abort_unless($product->tenant_id === $staff->tenant_id && $product->site_id === $site->id, 404);

    $siteIds = collect($payload['linkedSiteIds'] ?? [])
      ->map(fn ($id) => (int) $id)
      ->filter(fn (int $id) => $id > 0)
      ->unique()
      ->values();

    $validSiteIds = Site::query()
      ->where('tenant_id', $staff->tenant_id)
      ->whereIn('id', $siteIds)
      ->pluck('id');

    $scope = $product->scope_config ?? [];
    $scope['linkedSiteIds'] = $validSiteIds->all();
    $product->update(['scope_config' => $scope]);

    return [
      'cardProductId' => $product->id,
      'linkedSiteIds' => $validSiteIds->all(),
    ];
  }

  /**
   * @return list<int>
   */
  private function linkedSiteIds(CardProduct $product): array
  {
    $raw = $product->scope_config['linkedSiteIds'] ?? [];

    return collect($raw)
      ->map(fn ($id) => (int) $id)
      ->filter(fn (int $id) => $id > 0)
      ->unique()
      ->values()
      ->all();
  }
}
