<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Schedule\ScheduleBatchWriteService;
use App\Services\Schedule\StaffScheduleSessionAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffScheduleBatchController extends Controller
{
    public function batchCopy(
        Request $request,
        int $site,
        StaffScheduleSessionAccessService $access,
        ScheduleBatchWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.batch.copy', $siteModel->id);

        $payload = $request->validate([
            'commandKey' => ['required', 'uuid'],
            'sourceSessionIds' => ['sometimes', 'array', 'min:1'],
            'sourceSessionIds.*' => ['integer', 'min:1'],
            'sourceFrom' => ['sometimes', 'date'],
            'sourceTo' => ['required_with:sourceFrom', 'date', 'after:sourceFrom'],
            'targetFrom' => ['required_with:sourceFrom', 'date'],
            'dayOffset' => ['sometimes', 'integer'],
        ]);

        abort_if(
            empty($payload['sourceSessionIds']) && ! isset($payload['sourceFrom']),
            422,
            'SCHEDULE_BATCH_COPY_INPUT_INVALID',
        );

        if (! empty($payload['sourceSessionIds'])) {
            abort_unless(
                isset($payload['dayOffset']) || isset($payload['targetFrom']),
                422,
                'SCHEDULE_BATCH_COPY_INPUT_INVALID',
            );
        }

        $writerResult = $writer->batchCopy($staff, $siteModel, $payload);
        $replay = (bool) ($writerResult['replay'] ?? false);
        unset($writerResult['replay']);

        return ApiResponse::success($writerResult, $replay ? 200 : 201);
    }

    public function batchSuspend(
        Request $request,
        int $site,
        StaffScheduleSessionAccessService $access,
        ScheduleBatchWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.batch.suspend', $siteModel->id);

        $payload = $request->validate([
            'sessionIds' => ['required', 'array', 'min:1'],
            'sessionIds.*' => ['integer', 'min:1'],
            'reason' => ['sometimes', 'string', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ]);

        return ApiResponse::success($this->withoutReplay($writer->batchSuspend($staff, $siteModel, $payload)));
    }

    public function batchCancel(
        Request $request,
        int $site,
        StaffScheduleSessionAccessService $access,
        ScheduleBatchWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.batch.cancel', $siteModel->id);

        $payload = $request->validate([
            'sessionIds' => ['required', 'array', 'min:1'],
            'sessionIds.*' => ['integer', 'min:1'],
            'reason' => ['sometimes', 'string', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ]);

        return ApiResponse::success($this->withoutReplay($writer->batchCancel($staff, $siteModel, $payload)));
    }

    public function batchUnsuspend(
        Request $request,
        int $site,
        StaffScheduleSessionAccessService $access,
        ScheduleBatchWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.batch.suspend', $siteModel->id);

        $payload = $request->validate([
            'sessionIds' => ['required', 'array', 'min:1'],
            'sessionIds.*' => ['integer', 'min:1'],
            'reason' => ['sometimes', 'string', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ]);

        return ApiResponse::success($this->withoutReplay($writer->batchUnsuspend($staff, $siteModel, $payload)));
    }

    public function batchChangeCourse(
        Request $request,
        int $site,
        StaffScheduleSessionAccessService $access,
        ScheduleBatchWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.write', $siteModel->id);

        $payload = $request->validate([
            'sessionIds' => ['required', 'array', 'min:1'],
            'sessionIds.*' => ['integer', 'min:1'],
            'targetCourseId' => ['required', 'integer', 'min:1'],
            'commandKey' => ['required', 'uuid'],
        ]);

        return ApiResponse::success($this->withoutReplay($writer->batchChangeCourse($staff, $siteModel, $payload)));
    }

    private function withoutReplay(array $result): array
    {
        unset($result['replay']);

        return $result;
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
