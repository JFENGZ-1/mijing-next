<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Notifications\NotificationReminderService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffNotificationReminderController extends Controller
{
    public function anniversary(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        NotificationReminderService $reminders,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'notification.reminder.read', $siteModel->id);

        [$days, $memberStatus, $page, $perPage] = $this->listParams($request, NotificationReminderService::DEFAULT_ANNIVERSARY_DAYS, true);

        return ApiResponse::success($reminders->anniversary($staff, $siteModel, $days, $memberStatus, $page, $perPage));
    }

    public function noClass(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        NotificationReminderService $reminders,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'notification.reminder.read', $siteModel->id);

        [$days, , $page, $perPage] = $this->listParams($request, NotificationReminderService::DEFAULT_NO_CLASS_DAYS, false);

        return ApiResponse::success($reminders->noClass($staff, $siteModel, $days, $page, $perPage));
    }

    public function birthdays(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        NotificationReminderService $reminders,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'notification.reminder.read', $siteModel->id);

        [$days, $memberStatus, $page, $perPage] = $this->listParams($request, NotificationReminderService::DEFAULT_BIRTHDAY_DAYS, true);

        return ApiResponse::success($reminders->birthdays($staff, $siteModel, $days, $memberStatus, $page, $perPage));
    }

    public function visitors(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        NotificationReminderService $reminders,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'notification.reminder.read', $siteModel->id);

        [$days, , $page, $perPage] = $this->listParams($request, NotificationReminderService::DEFAULT_VISITOR_DAYS, false);

        return ApiResponse::success($reminders->visitors($staff, $siteModel, $days, $page, $perPage));
    }

    public function holidayDue(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        NotificationReminderService $reminders,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'notification.reminder.read', $siteModel->id);

        [$days, , $page, $perPage] = $this->listParams($request, NotificationReminderService::DEFAULT_HOLIDAY_DUE_DAYS, false);

        return ApiResponse::success($reminders->holidayDue($staff, $siteModel, $days, $page, $perPage));
    }

    /**
     * @return array{0: int, 1: string, 2: int, 3: int}
     */
    private function listParams(Request $request, int $defaultDays, bool $withMemberStatus): array
    {
        $rules = [
            'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];

        if ($withMemberStatus) {
            $rules['memberStatus'] = ['sometimes', 'string', 'in:valid,invalid,all'];
        }

        $request->validate($rules);

        return [
            $request->filled('days') ? max($request->integer('days'), 1) : $defaultDays,
            $withMemberStatus ? $request->string('memberStatus', 'valid')->toString() : 'valid',
            max($request->integer('page', 1), 1),
            min(max($request->integer('perPage', 20), 1), 50),
        ];
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
