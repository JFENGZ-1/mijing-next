<?php

namespace App\Console\Commands;

use App\Services\Compensation\ConsumptionSettlementService;
use Illuminate\Console\Command;

class FinalizePeriodConsumptionDays extends Command
{
    protected $signature = 'consumption:finalize-period-days
        {--grace=30 : 场馆本地日结束后的宽限分钟数}';

    protected $description = '按场馆时区封存已过宽限期的期限卡日耗卡桶';

    public function handle(ConsumptionSettlementService $settlements): int
    {
        $graceMinutes = max(0, (int) $this->option('grace'));
        $count = $settlements->finalizeAllDue(now(), $graceMinutes);

        $this->info("已封存 {$count} 个期限卡日耗卡桶。");

        return self::SUCCESS;
    }
}
