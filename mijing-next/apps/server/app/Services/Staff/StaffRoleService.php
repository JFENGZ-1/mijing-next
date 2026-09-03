<?php

namespace App\Services\Staff;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffRoleService
{
    /**
     * @return array<string, mixed>
     */
    public function show(Staff $actor, Role $role): array
    {
        $this->assertRoleAccessible($actor, $role);
        $role->loadMissing('permissions');

        return $this->presentRole($role, includePermissions: true);
    }

    /**
     * @param  array{name: string, permissionIds: list<int>, id?: int|null}  $payload
     * @return array<string, mixed>
     */
    public function upsert(Staff $actor, array $payload): array
    {
        $permissionIds = $this->resolvePermissionIds($payload['permissionIds']);

        return DB::transaction(function () use ($actor, $payload, $permissionIds) {
            if (! empty($payload['id'])) {
                $role = Role::query()
                    ->whereKey($payload['id'])
                    ->where('tenant_id', $actor->tenant_id)
                    ->firstOrFail();
                abort_if($role->is_system, 422, 'STAFF_ROLE_SYSTEM_IMMUTABLE');
                $role->update(['name' => $payload['name']]);
            } else {
                $role = Role::create([
                    'tenant_id' => $actor->tenant_id,
                    'name' => $payload['name'],
                    'code' => $this->nextCustomRoleCode($actor->tenant_id),
                    'is_system' => false,
                    'status' => 'active',
                ]);
            }

            $role->permissions()->sync($permissionIds);

            return $this->presentRole($role->fresh(['permissions']), includePermissions: true);
        });
    }

    /**
     * @param  list<int>  $permissionIds
     * @return list<int>
     */
    private function resolvePermissionIds(array $permissionIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $permissionIds)));
        abort_if($ids === [], 422, 'STAFF_ROLE_PERMISSIONS_REQUIRED');

        $existing = Permission::query()->whereIn('id', $ids)->pluck('id')->all();
        abort_unless(count($existing) === count($ids), 422, 'STAFF_ROLE_PERMISSION_INVALID');

        return $ids;
    }

    private function assertRoleAccessible(Staff $actor, Role $role): void
    {
        abort_unless(
            $role->tenant_id === $actor->tenant_id && $role->status === 'active',
            404,
            'RESOURCE_NOT_FOUND',
        );
    }

    private function nextCustomRoleCode(int $tenantId): string
    {
        do {
            $candidate = 'custom-'.Str::lower(Str::random(8));
        } while (Role::query()->where('tenant_id', $tenantId)->where('code', $candidate)->exists());

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRole(Role $role, bool $includePermissions): array
    {
        $data = [
            'id' => $role->id,
            'name' => $role->name,
            'code' => $role->code,
            'isSystem' => (bool) $role->is_system,
            'permissionCount' => $role->permissions->count(),
        ];

        if ($includePermissions) {
            $data['permissions'] = $role->permissions
                ->sortBy('code')
                ->map(fn (Permission $permission) => [
                    'id' => $permission->id,
                    'code' => $permission->code,
                    'name' => $permission->name,
                    'module' => $permission->module,
                ])
                ->values()
                ->all();
        }

        return $data;
    }
}
