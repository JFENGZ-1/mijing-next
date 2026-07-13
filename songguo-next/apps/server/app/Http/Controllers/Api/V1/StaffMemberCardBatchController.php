<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Cards\MemberCardBatchService;
use App\Services\Members\StaffMemberAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffMemberCardBatchController extends Controller
{
    public function batchBalanceAdjustments(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        MemberCardBatchService $batch,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.balance.adjust', $siteModel->id);

        $payload = $request->validate([
            'commandKey' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.memberCardId' => ['required', 'integer', 'min:1'],
            'items.*.commandKey' => ['required', 'uuid'],
            'items.*.direction' => ['required', Rule::in(['credit', 'debit'])],
            'items.*.amount' => ['required', 'numeric', 'min:0.01'],
            'items.*.reason' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::success($batch->batchBalanceAdjustments($staff, $siteModel, $payload), 201);
    }

    public function batchValidityExtensions(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        MemberCardBatchService $batch,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.validity.extend', $siteModel->id);

        $payload = $request->validate([
            'commandKey' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.memberCardId' => ['required', 'integer', 'min:1'],
            'items.*.commandKey' => ['required', 'uuid'],
            'items.*.validUntil' => ['required', 'date'],
            'items.*.reason' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::success($batch->batchValidityExtensions($staff, $siteModel, $payload), 201);
    }

    public function batchFreeze(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        MemberCardBatchService $batch,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.freeze', $siteModel->id);

        $payload = $request->validate([
            'commandKey' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.memberCardId' => ['required', 'integer', 'min:1'],
            'items.*.commandKey' => ['required', 'uuid'],
            'items.*.reason' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::success($batch->batchFreeze($staff, $siteModel, $payload), 201);
    }

    public function batchUnfreeze(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        MemberCardBatchService $batch,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.freeze', $siteModel->id);

        $payload = $request->validate([
            'commandKey' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.memberCardId' => ['required', 'integer', 'min:1'],
            'items.*.commandKey' => ['required', 'uuid'],
            'items.*.reason' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::success($batch->batchUnfreeze($staff, $siteModel, $payload), 201);
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
