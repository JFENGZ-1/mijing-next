<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemberCardReminderConfigRequest;
use App\Models\Staff;
use App\Services\Cards\MemberCardReminderService;
use App\Services\Members\StaffMemberAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffMemberCardReminderController extends Controller
{
    public function expiring(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        MemberCardReminderService $reminders,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.reminder.read', $siteModel->id);

        $withinDays = $request->filled('withinDays')
            ? max(1, $request->integer('withinDays'))
            : null;

        $paginator = $reminders->expiringQuery($staff, $siteModel, $withinDays)
            ->with('member.crmProfile')
            ->paginate(min(max($request->integer('perPage', 20), 1), 50));

        return ApiResponse::success($this->paginatedReminderResponse($reminders, $paginator, $reminders->configForSite($staff, $siteModel)));
    }

    public function zeroBalance(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        MemberCardReminderService $reminders,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.reminder.read', $siteModel->id);

        $paginator = $reminders->zeroBalanceQuery($staff, $siteModel)
            ->with('member.crmProfile')
            ->paginate(min(max($request->integer('perPage', 20), 1), 50));

        return ApiResponse::success($this->paginatedReminderResponse($reminders, $paginator, $reminders->configForSite($staff, $siteModel)));
    }

    public function pendingOpen(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        MemberCardReminderService $reminders,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.reminder.read', $siteModel->id);

        $paginator = $reminders->pendingOpenQuery($staff, $siteModel)
            ->with('member.crmProfile')
            ->paginate(min(max($request->integer('perPage', 20), 1), 50));

        return ApiResponse::success($this->paginatedReminderResponse($reminders, $paginator, $reminders->configForSite($staff, $siteModel)));
    }

    public function penalized(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        MemberCardReminderService $reminders,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.reminder.read', $siteModel->id);

        $paginator = $reminders->penalizedQuery($staff, $siteModel)
            ->with('member.crmProfile')
            ->paginate(min(max($request->integer('perPage', 20), 1), 50));

        return ApiResponse::success($this->paginatedReminderResponse($reminders, $paginator, $reminders->configForSite($staff, $siteModel)));
    }

    public function showConfig(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        MemberCardReminderService $reminders,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.reminder.config', $siteModel->id);

        return ApiResponse::success($reminders->configForSite($staff, $siteModel));
    }

    public function updateConfig(
        MemberCardReminderConfigRequest $request,
        int $site,
        StaffMemberAccessService $access,
        MemberCardReminderService $reminders,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.reminder.config', $siteModel->id);

        return ApiResponse::success(
            $reminders->saveConfig($staff, $siteModel, $request->validated()),
        );
    }

    private function paginatedReminderResponse(
        MemberCardReminderService $reminders,
        $paginator,
        array $config,
    ): array {
        return [
            'config' => $config,
            'items' => $reminders->reminderItems(collect($paginator->items())),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ];
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
