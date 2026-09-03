<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Models\Site;
use App\Models\Staff;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiteController extends Controller
{
    public function index(Request $request)
    {
        $staff = $this->activeStaff($request);

        return ApiResponse::success(
            $staff->sites()->where('sites.tenant_id', $staff->tenant_id)->orderBy('name')->get()->map(fn (Site $site) => $this->siteData($site)),
        );
    }

    public function store(StoreSiteRequest $request)
    {
        $staff = $this->activeStaff($request);
        abort_unless($staff->hasPermission('organization.site.manage'), 403, 'PERMISSION_DENIED');

        $site = DB::transaction(function () use ($request, $staff) {
            $site = Site::create([...$request->validated(), 'tenant_id' => $staff->tenant_id]);
            $staff->sites()->syncWithoutDetaching([$site->id => ['tenant_id' => $staff->tenant_id, 'is_primary' => false]]);
            return $site;
        });

        return ApiResponse::success($this->siteData($site), 201);
    }

    public function show(Request $request, Site $site)
    {
        $staff = $this->activeStaff($request);
        $this->assertSiteAccess($staff, $site);

        return ApiResponse::success($this->siteData($site));
    }

    public function update(UpdateSiteRequest $request, Site $site)
    {
        $staff = $this->activeStaff($request);
        $this->assertSiteAccess($staff, $site);
        abort_unless($staff->hasPermission('organization.site.manage', $site->id), 403, 'PERMISSION_DENIED');

        $updated = Site::query()
            ->whereKey($site->id)
            ->where('tenant_id', $staff->tenant_id)
            ->where('version', $request->integer('version'))
            ->update([...$request->safe()->except('version'), 'version' => DB::raw('version + 1')]);

        abort_if($updated !== 1, 409, 'VERSION_CONFLICT');

        return ApiResponse::success($this->siteData($site->fresh()));
    }

    public function destroy(Request $request, Site $site)
    {
        $staff = $this->activeStaff($request);
        $this->assertSiteAccess($staff, $site);
        abort_unless($staff->hasPermission('organization.site.manage', $site->id), 403, 'PERMISSION_DENIED');

        $site->update(['status' => 'disabled', 'version' => $site->version + 1]);

        return ApiResponse::success(['disabled' => true]);
    }

    private function activeStaff(Request $request): Staff
    {
        $staff = $request->attributes->get('staff_context');
        abort_unless($staff, 403, 'STAFF_ACCESS_DENIED');
        return $staff;
    }

    private function assertSiteAccess(Staff $staff, Site $site): void
    {
        abort_unless($site->tenant_id === $staff->tenant_id, 404);
        abort_unless($staff->sites()->whereKey($site->id)->exists(), 404);
    }

    private function siteData(Site $site): array
    {
        return $site->only([
            'id', 'name', 'code', 'status', 'phone', 'address',
            'longitude', 'latitude', 'timezone', 'version',
        ]);
    }
}
