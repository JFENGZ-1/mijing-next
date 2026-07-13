<?php

namespace App\Console\Commands;

use App\Models\Staff;
use App\Services\Auth\StaffWechatBindingService;
use App\Services\Wechat\WechatAuthService;
use Illuminate\Console\Command;
use RuntimeException;

class BindStaffWechatCode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staff:bind-wechat-code {code : One-time wx.login code} {--employee-no=ADMIN001}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Exchange a staff Mini Program code and explicitly bind the identity to a staff record';

    /**
     * Execute the console command.
     */
    public function handle(WechatAuthService $wechat, StaffWechatBindingService $binding): int
    {
        $staff = Staff::where('employee_no', $this->option('employee-no'))->first();
        if (! $staff) {
            $this->error('Staff record was not found.');
            return self::FAILURE;
        }

        try {
            $session = $wechat->exchangeCode('staff', $this->argument('code'));
        } catch (RuntimeException $exception) {
            $this->error("WeChat code exchange failed: {$exception->getMessage()}");
            return self::FAILURE;
        }

        $binding->bindSessionToStaff($session, $staff);

        $this->info("Staff #{$staff->id} WeChat identity is bound.");
        return self::SUCCESS;
    }
}
