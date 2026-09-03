<?php

namespace App\Services\Chain;

use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Collection;

class ChainStaffService
{
    /**
     * HQ staff: tenant-wide role assignment or access to every active branch.
     *
     * @return array{items: list<array<string, mixed>>, activeCount: int, departedCount: int}
     */
    public function list(Staff $actor): array
    {
        $activeSiteIds = Site::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('status', 'active')
            ->pluck('id');

        $staffMembers = Staff::query()
            ->with(['roles', 'sites'])
            ->where('tenant_id', $actor->tenant_id)
            ->orderByRaw("CASE WHEN staff.status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('staff.name')
            ->get()
            ->filter(fn (Staff $member) => $this->isHeadquartersStaff($member, $activeSiteIds))
            ->values();

        $items = $staffMembers
            ->map(fn (Staff $member) => [
                'id' => $member->id,
                'displayName' => $member->name,
                'employeeNo' => $member->employee_no,
                'status' => $member->status,
                'roleName' => $this->primaryRoleName($member),
                'siteCount' => $member->sites()->whereIn('sites.id', $activeSiteIds)->count(),
                'hasTenantWideRole' => $member->roles->contains(fn (Role $role) => $role->pivot->site_id === null),
            ])
            ->all();

        return [
            'items' => $items,
            'activeCount' => $staffMembers->where('status', 'active')->count(),
            'departedCount' => $staffMembers->where('status', 'departed')->count(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $activeSiteIds
     */
    private function isHeadquartersStaff(Staff $member, $activeSiteIds): bool
    {
        if ($member->roles->contains(fn (Role $role) => $role->pivot->site_id === null)) {
            return true;
        }

        if ($activeSiteIds->count() < 2) {
            return false;
        }

        $assignedCount = $member->sites()
            ->whereIn('sites.id', $activeSiteIds)
            ->count();

        return $assignedCount >= $activeSiteIds->count();
    }

    private function primaryRoleName(Staff $member): ?string
    {
        $role = $member->roles->first(fn (Role $entry) => $entry->pivot->site_id === null)
            ?? $member->roles->first();

        return $role?->name;
    }
}
