<?php

namespace App\Services\Auth;

use App\Models\Account;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;

class DemoStaffProvisioningService
{
    public function provision(Account $account, string $openid): ?Staff
    {
        if (! config('wechat.staff_demo.auto_provision', false)) {
            return null;
        }

        $tenant = Tenant::query()
            ->where('code', config('wechat.staff_demo.tenant_code', 'mijing'))
            ->where('status', 'active')
            ->first();
        if (! $tenant) {
            return null;
        }

        $site = Site::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', config('wechat.staff_demo.site_code', 'main'))
            ->where('status', 'active')
            ->first();
        if (! $site) {
            return null;
        }

        $identitySuffix = strtoupper(substr(hash('sha256', $openid), 0, 10));
        $staffName = $account->display_name ?: "演示员工{$identitySuffix}";
        $account->forceFill(['display_name' => $staffName])->save();

        $staff = Staff::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'account_id' => $account->id,
            ],
            [
                'employee_no' => "DEMO-{$identitySuffix}",
                'name' => $staffName,
                'status' => 'active',
                'joined_on' => today(),
                'left_on' => null,
            ],
        );

        return $this->grantFullAccess($staff);
    }

    public function grantFullAccess(Staff $staff): Staff
    {
        if (! config('wechat.staff_demo.auto_provision', false)) {
            return $staff;
        }

        $tenant = Tenant::query()
            ->whereKey($staff->tenant_id)
            ->where('code', config('wechat.staff_demo.tenant_code', 'mijing'))
            ->where('status', 'active')
            ->first();
        if (! $tenant) {
            return $staff;
        }

        $site = Site::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', config('wechat.staff_demo.site_code', 'main'))
            ->where('status', 'active')
            ->first();
        if (! $site) {
            return $staff;
        }

        $role = Role::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'demo-operator'],
            ['name' => '演示操作员', 'is_system' => true, 'status' => 'active'],
        );
        if ($role->status !== 'active') {
            $role->forceFill(['status' => 'active'])->save();
        }
        $role->permissions()->sync(Permission::query()->pluck('id'));

        $staff->sites()->syncWithoutDetaching([
            $site->id => ['tenant_id' => $tenant->id, 'is_primary' => true],
        ]);
        $staff->roles()->syncWithoutDetaching([
            $role->id => ['tenant_id' => $tenant->id, 'site_id' => null],
        ]);

        return $staff->fresh(['sites', 'roles.permissions']);
    }
}
