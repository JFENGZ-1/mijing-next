<?php

namespace App\Services\Chain;

use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Collection;

class ChainAccessService
{
    public function assertChainRead(Staff $staff): void
    {
        abort_unless($staff->hasPermission('org.chain.read'), 403, 'PERMISSION_DENIED');
    }

    public function assertChainManagementRead(Staff $staff): void
    {
        abort_unless(
            $staff->hasPermission('org.chain.read')
            || $staff->hasPermission('tenant.settings.chain.read'),
            403,
            'PERMISSION_DENIED',
        );
    }

    /**
     * All active sites in the staff tenant (maps legacy getLinkSite roster).
     *
     * @return Collection<int, Site>
     */
    public function tenantSites(Staff $staff): Collection
    {
        return Site::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    /**
     * Resolve report scope: explicit siteIds or staff-assigned sites.
     * Staff must belong to every requested site (404 otherwise).
     *
     * @return Collection<int, Site>
     */
    public function resolveReportSites(Staff $staff, ?string $siteIds): Collection
    {
        $assignedSiteIds = $staff->sites()
            ->where('sites.tenant_id', $staff->tenant_id)
            ->where('sites.status', 'active')
            ->pluck('sites.id');

        if ($siteIds === null || trim($siteIds) === '') {
            return Site::query()
                ->whereIn('id', $assignedSiteIds)
                ->where('tenant_id', $staff->tenant_id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get();
        }

        $requestedIds = collect(explode(',', $siteIds))
            ->map(fn (string $id) => (int) trim($id))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        abort_if($requestedIds->isEmpty(), 422, 'INVALID_SITE_IDS');

        foreach ($requestedIds as $siteId) {
            abort_unless($assignedSiteIds->contains($siteId), 404);
        }

        $sites = Site::query()
            ->whereIn('id', $requestedIds)
            ->where('tenant_id', $staff->tenant_id)
            ->where('status', 'active')
            ->get();

        return $requestedIds
            ->map(fn (int $siteId) => $sites->firstWhere('id', $siteId))
            ->filter();
    }

    public function staffHasSiteAccess(Staff $staff, int $siteId): bool
    {
        return $staff->sites()
            ->where('sites.tenant_id', $staff->tenant_id)
            ->whereKey($siteId)
            ->exists();
    }
}
