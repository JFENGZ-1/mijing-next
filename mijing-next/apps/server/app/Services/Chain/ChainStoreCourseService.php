<?php

namespace App\Services\Chain;

use App\Models\CardProduct;
use App\Models\Site;
use App\Models\Staff;

class ChainStoreCourseService
{
    /**
     * Cross-site card products with per-site course scope counts (maps legacy card-subject).
     *
     * @return array{items: list<array<string, mixed>>}
     */
    public function list(Staff $staff): array
    {
        $sites = Site::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $products = CardProduct::query()
            ->withCount('courseScopes')
            ->where('tenant_id', $staff->tenant_id)
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $items = $products
            ->filter(fn (CardProduct $product) => count($this->linkedSiteIds($product)) > 0)
            ->map(function (CardProduct $product) use ($sites) {
                $linkedSiteIds = $this->linkedSiteIds($product);

                return [
                    'cardProductId' => $product->id,
                    'name' => $product->name,
                    'homeSiteId' => $product->site_id,
                    'linkedSiteIds' => $linkedSiteIds,
                    'courseScopeCount' => (int) $product->course_scopes_count,
                    'sites' => $sites
                        ->filter(fn (Site $site) => in_array($site->id, $linkedSiteIds, true))
                        ->map(fn (Site $site) => [
                            'siteId' => $site->id,
                            'siteName' => $site->name,
                            'editRoute' => '/subpackages/settings/card-products/edit',
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return ['items' => $items];
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
