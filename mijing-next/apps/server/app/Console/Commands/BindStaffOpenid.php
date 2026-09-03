<?php

namespace App\Console\Commands;

use App\Models\Staff;
use App\Services\Auth\StaffWechatBindingService;
use Illuminate\Console\Command;

class BindStaffOpenid extends Command
{
    protected $signature = 'staff:bind-openid {openid : Staff mini program openid from DevTools status or wx.login} {--employee-no=ADMIN001}';

    protected $description = 'Bind a staff mini program openid to an existing staff record (local/testing only)';

    public function handle(StaffWechatBindingService $binding): int
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            $this->error('This command is only available in local or testing environments.');

            return self::FAILURE;
        }

        $appid = config('wechat.apps.staff.appid');
        if (! is_string($appid) || $appid === '') {
            $this->error('WECHAT_STAFF_APPID is not configured.');

            return self::FAILURE;
        }

        $staff = Staff::query()->where('employee_no', $this->option('employee-no'))->first();
        if (! $staff) {
            $this->error('Staff record was not found.');

            return self::FAILURE;
        }

        $binding->bindOpenidToStaff($appid, $this->argument('openid'), $staff);
        $this->info("Staff #{$staff->id} is bound to openid {$this->argument('openid')}.");

        return self::SUCCESS;
    }
}
