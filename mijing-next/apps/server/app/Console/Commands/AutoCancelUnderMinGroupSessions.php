<?php

namespace App\Console\Commands;

use App\Services\Booking\GroupSessionAutoCancelService;
use Illuminate\Console\Command;

class AutoCancelUnderMinGroupSessions extends Command
{
    protected $signature = 'schedule:auto-cancel-under-min {--limit=200 : 单次最多取消预约条数}';

    protected $description = '团课人数不足自动取消：在课前 N 分钟检查最低开课人数并取消预约';

    public function handle(GroupSessionAutoCancelService $service): int
    {
        $count = $service->run((int) $this->option('limit'));
        $this->info("自动取消低人数团课预约 {$count} 条。");

        return self::SUCCESS;
    }
}
