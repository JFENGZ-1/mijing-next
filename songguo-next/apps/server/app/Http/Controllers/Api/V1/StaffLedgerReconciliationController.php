<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Reconciliation\LedgerReconciliationJobService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffLedgerReconciliationController extends Controller
{
    public function createJob(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        LedgerReconciliationJobService $jobs,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'ledger.reconciliation.write', $siteModel->id);

        $payload = $request->validate([
            'commandKey' => ['required', 'uuid'],
            'fromDate' => ['required', 'date'],
            'toDate' => ['required', 'date', 'after_or_equal:fromDate'],
            'dryRun' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::success($jobs->createJob($staff, $siteModel, $request, $payload), 201);
    }

    public function listJobs(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        LedgerReconciliationJobService $jobs,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'ledger.reconciliation.read', $siteModel->id);

        $page = max($request->integer('page', 1), 1);
        $perPage = min(max($request->integer('perPage', 20), 1), 50);

        return ApiResponse::success($jobs->listJobs($staff, $siteModel, $page, $perPage));
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
