<?php

namespace App\Services\Admin;

use App\Models\Account;
use App\Models\CardProduct;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\MemberCrmProfile;
use App\Models\MemberProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminDemoDataService
{
    /** @return array<string, mixed> */
    public function generate(): array
    {
        abort_unless(config('wechat.staff_demo.auto_provision', false), 409, 'STAFF_DEMO_MODE_DISABLED');

        try {
            return Cache::lock('admin-demo-data:generate', 30)->block(5, function () {
                [$tenant, $site, $staff] = DB::transaction(fn () => $this->ensureCoreFixtures());

                $previousEnvironment = config('app.env');
                $previousTargetSite = config('demo.target_site_id');
                config()->set('app.env', 'local');
                config()->set('demo.target_site_id', $site->id);
                try {
                    app(DatabaseSeeder::class)
                        ->setContainer(app())
                        ->__invoke();
                } finally {
                    config()->set('app.env', $previousEnvironment);
                    config()->set('demo.target_site_id', $previousTargetSite);
                }

                $this->grantAllPermissions($tenant, $staff);

                return [
                    'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'code' => $tenant->code],
                    'site' => ['id' => $site->id, 'name' => $site->name, 'code' => $site->code],
                    'counts' => [
                        'staff' => Staff::query()->where('tenant_id', $tenant->id)->count(),
                        'members' => Member::query()->where('tenant_id', $tenant->id)->count(),
                        'cardProducts' => CardProduct::query()->where('tenant_id', $tenant->id)->where('site_id', $site->id)->count(),
                        'memberCards' => MemberCard::query()->where('tenant_id', $tenant->id)->where('site_id', $site->id)->count(),
                        'orders' => MemberCardOrder::query()->where('tenant_id', $tenant->id)->where('site_id', $site->id)->count(),
                        'courses' => Course::query()->where('tenant_id', $tenant->id)->where('site_id', $site->id)->count(),
                        'scheduleSessions' => ScheduleSession::query()->where('tenant_id', $tenant->id)->where('site_id', $site->id)->count(),
                    ],
                    'generatedAt' => now()->toISOString(),
                ];
            });
        } catch (LockTimeoutException) {
            abort(409, 'DEMO_DATA_GENERATION_BUSY');
        }
    }

    /** @return array{0: Tenant, 1: Site, 2: Staff} */
    private function ensureCoreFixtures(): array
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['code' => config('wechat.staff_demo.tenant_code', 'mijing')],
            ['name' => '觅境线上演示', 'status' => 'active', 'timezone' => 'Asia/Shanghai'],
        );
        if ($tenant->status !== 'active') {
            $tenant->forceFill(['status' => 'active'])->save();
        }

        $site = Site::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => config('wechat.staff_demo.site_code', 'main')],
            ['name' => '觅境演示场馆', 'status' => 'active', 'timezone' => 'Asia/Shanghai'],
        );
        if ($site->status !== 'active') {
            $site->forceFill(['status' => 'active'])->save();
        }

        $staff = Staff::query()
            ->where('tenant_id', $tenant->id)
            ->where('employee_no', 'DEMO-OWNER')
            ->first();
        if (! $staff) {
            $account = Account::query()->create(['display_name' => '演示管理员', 'status' => 'active']);
            $staff = Staff::query()->create([
                'tenant_id' => $tenant->id,
                'account_id' => $account->id,
                'employee_no' => 'DEMO-OWNER',
                'name' => '演示管理员',
                'status' => 'active',
                'joined_on' => today(),
            ]);
        }
        $staff->sites()->syncWithoutDetaching([
            $site->id => ['tenant_id' => $tenant->id, 'is_primary' => true],
        ]);
        if ($site->owner_staff_id === null) {
            $site->forceFill(['owner_staff_id' => $staff->id])->save();
        }

        $member = Member::query()->firstOrNew([
            'tenant_id' => $tenant->id,
            'member_no' => 'DEMO-MEMBER-001',
        ]);
        if (! $member->exists) {
            $memberAccount = Account::query()->create(['display_name' => '演示会员', 'status' => 'active']);
            $member->fill([
                'account_id' => $memberAccount->id,
                'status' => 'active',
                'source' => 'admin-demo',
                'registration_site_id' => $site->id,
                'home_site_id' => $site->id,
                'owner_staff_id' => $staff->id,
                'app_access_status' => 'allowed',
                'joined_at' => now(),
            ])->save();
        }
        MemberProfile::query()->updateOrCreate(
            ['account_id' => $member->account_id],
            ['display_name' => '演示会员'],
        );
        MemberCrmProfile::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'member_id' => $member->id],
            ['name' => '演示会员'],
        );
        $member->sites()->syncWithoutDetaching([
            $site->id => [
                'tenant_id' => $tenant->id,
                'relationship_type' => 'registered',
                'status' => 'active',
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ],
        ]);

        return [$tenant, $site, $staff];
    }

    private function grantAllPermissions(Tenant $tenant, Staff $staff): void
    {
        $role = Role::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'demo-operator'],
            ['name' => '演示操作员', 'is_system' => true, 'status' => 'active'],
        );
        $role->forceFill(['status' => 'active'])->save();
        $role->permissions()->sync(Permission::query()->pluck('id'));
        $staff->roles()->syncWithoutDetaching([
            $role->id => ['tenant_id' => $tenant->id, 'site_id' => null],
        ]);
    }
}
