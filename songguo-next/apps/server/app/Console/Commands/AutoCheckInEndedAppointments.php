<?php

namespace App\Console\Commands;

use App\Services\Booking\AppointmentFulfillmentService;
use Illuminate\Console\Command;

class AutoCheckInEndedAppointments extends Command
{
    protected $signature = 'appointments:auto-check-in {--limit=500 : 单次最多处理条数}';

    protected $description = '自动签到：把已下课且仍 confirmed 的预约批量转 completed（对标原版「下课5分钟内自动签到」）';

    public function handle(AppointmentFulfillmentService $fulfillment): int
    {
        $count = $fulfillment->autoCheckInEndedSessions((int) $this->option('limit'));

        $this->info("自动签到完成 {$count} 条预约。");

        return self::SUCCESS;
    }
}
