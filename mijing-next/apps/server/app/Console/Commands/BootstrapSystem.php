<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Services\Auth\StaffWechatBindingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BootstrapSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:bootstrap
        {--tenant-name=觅境约课 : Tenant display name}
        {--tenant-code=mijing : Stable tenant code}
        {--site-name=示范场馆 : Initial site name}
        {--site-code=main : Initial site code}
        {--staff-name=系统管理员 : Initial administrator name}
        {--employee-no=ADMIN001 : Initial employee number}
        {--issue-token : Print a one-time local API token}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Idempotently create the first tenant, site, owner role and administrator';

    /**
     * Execute the console command.
     */
    public function handle(StaffWechatBindingService $binding): int
    {
        $result = DB::transaction(function () {
            $tenant = Tenant::firstOrCreate(
                ['code' => $this->option('tenant-code')],
                ['name' => $this->option('tenant-name'), 'status' => 'active', 'timezone' => 'Asia/Shanghai'],
            );
            $account = Account::firstOrCreate(
                ['mobile' => null, 'display_name' => $this->option('staff-name')],
                ['status' => 'active'],
            );
            $site = Site::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $this->option('site-code')],
                ['name' => $this->option('site-name'), 'status' => 'active', 'timezone' => 'Asia/Shanghai'],
            );
            $staff = Staff::firstOrCreate(
                ['tenant_id' => $tenant->id, 'employee_no' => $this->option('employee-no')],
                ['account_id' => $account->id, 'name' => $this->option('staff-name'), 'status' => 'active', 'joined_on' => today()],
            );
            $role = Role::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'owner'],
                ['name' => '租户所有者', 'is_system' => true, 'status' => 'active'],
            );

            $role->permissions()->sync(Permission::query()->pluck('id'));
            $staff->sites()->syncWithoutDetaching([$site->id => ['tenant_id' => $tenant->id, 'is_primary' => true]]);
            $staff->roles()->syncWithoutDetaching([$role->id => ['tenant_id' => $tenant->id, 'site_id' => null]]);
            if ($site->owner_staff_id === null) {
                $site->update(['owner_staff_id' => $staff->id]);
            }

            return compact('tenant', 'account', 'site', 'staff');
        });

        $this->info("Tenant #{$result['tenant']->id}, site #{$result['site']->id}, staff #{$result['staff']->id} are ready.");

        $devOpenid = config('wechat.dev_staff_openid');
        $staffAppid = config('wechat.apps.staff.appid');
        if (
            in_array(config('app.env'), ['local', 'testing'], true)
            && is_string($devOpenid) && $devOpenid !== ''
            && is_string($staffAppid) && $staffAppid !== ''
        ) {
            $binding->bindOpenidToStaff($staffAppid, $devOpenid, $result['staff']);
            $this->info('DevTools staff openid is bound to the bootstrap administrator.');
        } else {
            $this->line('Staff DevTools login: bind your openid once with `php artisan staff:bind-openid <openid>` or `php artisan staff:bind-wechat-code <wx.login code>`.');
        }

        if ($this->option('issue-token')) {
            $result['account']->tokens()->where('name', 'local-bootstrap')->delete();
            $token = $result['account']->createToken('local-bootstrap', ['api'], now()->addDay());
            $this->warn('Local token (shown once, expires in 24 hours):');
            $this->line($token->plainTextToken);
        }

        return self::SUCCESS;
    }
}
