<?php

namespace App\Services\Cards;

use App\Enums\CardProductCatalogStatus;
use App\Models\CardProduct;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;

class StaffCardProductAccessService
{
    public function assertPermission(Staff $staff, string $permission, int $siteId): void
    {
        abort_unless($staff->hasPermission($permission, $siteId), 403, 'PERMISSION_DENIED');
    }

    public function site(Staff $staff, int $siteId): Site
    {
        return Site::query()
            ->whereKey($siteId)
            ->where('tenant_id', $staff->tenant_id)
            ->where('status', 'active')
            ->whereHas('staff', fn ($query) => $query
                ->whereKey($staff->id)
                ->where('site_staff.tenant_id', $staff->tenant_id))
            ->firstOrFail();
    }

    public function catalogQuery(Staff $staff, Site $site, ?CardProductCatalogStatus $catalogStatus = null): Builder
    {
        return CardProduct::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('catalog_status', $catalogStatus ?? CardProductCatalogStatus::Active);
    }

    public function product(Staff $staff, Site $site, int $cardProductId): CardProduct
    {
        return CardProduct::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereKey($cardProductId)
            ->firstOrFail();
    }
}
