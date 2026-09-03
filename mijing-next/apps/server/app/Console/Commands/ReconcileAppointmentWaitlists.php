<?php

namespace App\Console\Commands;

use App\Services\Booking\AppointmentWriteService;
use Illuminate\Console\Command;

class ReconcileAppointmentWaitlists extends Command
{
    protected $signature = 'appointments:reconcile-waitlists {--limit=200 : 单次最多扫描的场次数}';

    protected $description = '补偿取消后未成功的候补转正，按排队顺序幂等补位';

    public function handle(AppointmentWriteService $appointments): int
    {
        $result = $appointments->reconcileWaitlists((int) $this->option('limit'));
        $this->info("已扫描 {$result['sessionsScanned']} 个场次，补位 {$result['appointmentsPromoted']} 个候补预约，取消 {$result['appointmentsCancelled']} 个确定无法转正的候补。");

        return self::SUCCESS;
    }
}
