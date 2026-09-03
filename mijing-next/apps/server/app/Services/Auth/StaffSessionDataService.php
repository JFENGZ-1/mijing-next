<?php

namespace App\Services\Auth;

use App\Models\Staff;

class StaffSessionDataService
{
    public function data(Staff $staff): array
    {
        $roles = $staff->roles;

        return [
            'id' => $staff->id,
            'name' => $staff->name,
            'tenantId' => $staff->tenant_id,
            'sites' => $staff->sites->map(function ($site) use ($roles) {
                $permissions = $roles
                    ->filter(fn ($role) => $role->pivot->site_id === null || (int) $role->pivot->site_id === $site->id)
                    ->flatMap(fn ($role) => $role->permissions)
                    ->pluck('code')->unique()->values();

                return [
                    'id' => $site->id,
                    'name' => $site->name,
                    'status' => $site->status,
                    'permissions' => $permissions,
                ];
            })->values(),
            'permissions' => $roles->flatMap(fn ($role) => $role->permissions)->pluck('code')->unique()->values(),
        ];
    }
}
