<?php

namespace App\Console\Commands;

use App\Services\Compensation\ConsumptionDomainBackfillService;
use Illuminate\Console\Command;

class BackfillConsumptionDomain extends Command
{
    protected $signature = 'consumption:backfill-domain
        {--apply : 实际写入；默认只做 dry-run}
        {--tenant= : 只处理指定 tenant id}
        {--limit=1000 : 每类记录的最大扫描数}';

    protected $description = '安全回填价值批次与预约预占，不二次扣权益且不追溯生成历史提成';

    public function handle(ConsumptionDomainBackfillService $backfill): int
    {
        $apply = (bool) $this->option('apply');
        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $stats = $backfill->run($apply, $tenantId, (int) $this->option('limit'));

        $this->components->info($apply ? '回填完成。' : 'Dry-run 完成；未写入任何数据。');
        $this->table(['metric', 'count'], collect($stats)->map(fn ($count, $metric) => [$metric, $count])->values());

        return self::SUCCESS;
    }
}
