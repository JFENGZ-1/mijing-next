<?php

require __DIR__ . '/../../mijing-next/apps/server/vendor/autoload.php';
$app = require __DIR__ . '/../../mijing-next/apps/server/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\ExportJobStatus;
use App\Enums\ExportJobType;
use App\Models\ExportJob;
use App\Models\Staff;

$staff = Staff::query()->where('employee_no', 'ADMIN001')->firstOrFail();
$siteId = 2;

$job = ExportJob::query()->create([
    'tenant_id' => $staff->tenant_id,
    'site_id' => $siteId,
    'type' => ExportJobType::MemberExport,
    'status' => ExportJobStatus::Processing,
    'requested_by_staff_id' => $staff->id,
    'filters' => [],
]);

echo json_encode(['ok' => true, 'jobId' => $job->id, 'siteId' => $siteId], JSON_UNESCAPED_UNICODE) . PHP_EOL;
