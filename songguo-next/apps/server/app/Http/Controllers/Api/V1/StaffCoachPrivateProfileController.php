<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Catalog\CoachPrivateProfileService;
use App\Services\Catalog\StaffCourseCatalogAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffCoachPrivateProfileController extends Controller
{
    public function index(
        Request $request,
        int $site,
        StaffCourseCatalogAccessService $access,
        CoachPrivateProfileService $profiles,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'course-catalog.read', $siteModel->id);

        return ApiResponse::success(['items' => $profiles->list($staff, $siteModel)]);
    }

    public function store(
        Request $request,
        int $site,
        StaffCourseCatalogAccessService $access,
        CoachPrivateProfileService $profiles,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'course-catalog.write', $siteModel->id);

        $payload = $request->validate([
            'coachStaffId' => ['required', 'integer', 'min:1'],
            'tagText' => ['nullable', 'string', 'max:40'],
            'experience' => ['nullable', 'string', 'max:2500'],
            'specialty' => ['nullable', 'string', 'max:2500'],
            'bookingWindows' => ['required', 'array', 'min:1'],
            'bookingWindows.*.days' => ['required', 'array', 'min:1'],
            'bookingWindows.*.days.*' => ['integer', 'min:1', 'max:7'],
            'bookingWindows.*.start' => ['required', 'string', 'date_format:H:i'],
            'bookingWindows.*.end' => ['required', 'string', 'date_format:H:i'],
            'subjectMode' => ['sometimes', 'string', 'in:uniform,per_course'],
            'uniformDurationMinutes' => ['sometimes', 'integer', 'min:1', 'max:600'],
        ]);

        return ApiResponse::success($profiles->create($staff, $siteModel, $payload), 201);
    }

    public function update(
        Request $request,
        int $site,
        int $profile,
        StaffCourseCatalogAccessService $access,
        CoachPrivateProfileService $profiles,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'course-catalog.write', $siteModel->id);
        $profileModel = $profiles->find($staff, $siteModel, $profile);

        $payload = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'tagText' => ['sometimes', 'nullable', 'string', 'max:40'],
            'experience' => ['sometimes', 'nullable', 'string', 'max:2500'],
            'specialty' => ['sometimes', 'nullable', 'string', 'max:2500'],
            'bookingWindows' => ['sometimes', 'array', 'min:1'],
            'bookingWindows.*.days' => ['required_with:bookingWindows', 'array', 'min:1'],
            'bookingWindows.*.days.*' => ['integer', 'min:1', 'max:7'],
            'bookingWindows.*.start' => ['required_with:bookingWindows', 'string', 'date_format:H:i'],
            'bookingWindows.*.end' => ['required_with:bookingWindows', 'string', 'date_format:H:i'],
            'subjectMode' => ['sometimes', 'string', 'in:uniform,per_course'],
            'uniformDurationMinutes' => ['sometimes', 'integer', 'min:1', 'max:600'],
        ]);

        return ApiResponse::success($profiles->update($staff, $siteModel, $profileModel, $payload));
    }

    /**
     * 一次性保存（对标原版 savePrivateCourse）：档案 + 课目 + 卡扣费整体提交。
     */
    public function saveFull(
        Request $request,
        int $site,
        StaffCourseCatalogAccessService $access,
        CoachPrivateProfileService $profiles,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'course-catalog.write', $siteModel->id);

        $payload = $request->validate([
            'profileId' => ['sometimes', 'integer', 'min:1'],
            'version' => ['required_with:profileId', 'integer', 'min:1'],
            'coachStaffId' => ['required_without:profileId', 'integer', 'min:1'],
            'tagText' => ['nullable', 'string', 'max:40'],
            'experience' => ['nullable', 'string', 'max:2500'],
            'specialty' => ['nullable', 'string', 'max:2500'],
            'bookingWindows' => ['required', 'array', 'min:1'],
            'bookingWindows.*.days' => ['required', 'array', 'min:1'],
            'bookingWindows.*.days.*' => ['integer', 'min:1', 'max:7'],
            'bookingWindows.*.start' => ['required', 'string', 'date_format:H:i'],
            'bookingWindows.*.end' => ['required', 'string', 'date_format:H:i'],
            'subjectMode' => ['required', 'string', 'in:uniform,per_course'],
            'uniformDurationMinutes' => ['sometimes', 'integer', 'min:1', 'max:600'],
            'uniformFeeList' => ['sometimes', 'array'],
            'uniformFeeList.*.cardProductId' => ['required', 'integer', 'min:1'],
            'uniformFeeList.*.deductAmount' => ['nullable', 'numeric', 'min:0'],
            'courses' => ['sometimes', 'array'],
            'courses.*.id' => ['sometimes', 'integer', 'min:1'],
            'courses.*.name' => ['required', 'string', 'max:120'],
            'courses.*.durationMinutes' => ['required', 'integer', 'min:1', 'max:600'],
            'courses.*.feeList' => ['sometimes', 'array'],
            'courses.*.feeList.*.cardProductId' => ['required', 'integer', 'min:1'],
            'courses.*.feeList.*.deductAmount' => ['nullable', 'numeric', 'min:0'],
        ]);

        return ApiResponse::success($profiles->saveFull($staff, $siteModel, $payload));
    }

    /**
     * 时间槽（对标原版 getDrainerTimeList）：代约弹窗按日期+课目拉取可选开始时间。
     */
    public function timeSlots(
        Request $request,
        int $site,
        int $profile,
        StaffCourseCatalogAccessService $access,
        CoachPrivateProfileService $profiles,
        \App\Services\Booking\StaffBookingAppointmentAccessService $bookingAccess,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $bookingAccess->assertPermission($staff, 'booking.appointment.create', $siteModel->id);
        $profileModel = $profiles->find($staff, $siteModel, $profile);

        $payload = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'courseId' => ['sometimes', 'integer', 'min:1'],
            'excludeSessionId' => ['sometimes', 'integer', 'min:1'],
        ]);

        return ApiResponse::success($profiles->timeSlots(
            $siteModel,
            $profileModel,
            $payload['date'],
            (int) ($payload['courseId'] ?? 0),
            isset($payload['excludeSessionId']) ? (int) $payload['excludeSessionId'] : null,
        ));
    }

    /**
     * 员工代约私教（预约时间制）：在教练可约时段内动态生成 private session 并预约。
     */
    public function book(
        Request $request,
        int $site,
        int $profile,
        StaffCourseCatalogAccessService $access,
        CoachPrivateProfileService $profiles,
        \App\Services\Booking\StaffBookingAppointmentAccessService $bookingAccess,
        \App\Services\Booking\BookingPayableCardService $payableCards,
        \App\Services\Booking\AppointmentWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $bookingAccess->assertPermission($staff, 'booking.appointment.create', $siteModel->id);
        $profileModel = $profiles->find($staff, $siteModel, $profile);

        $payload = $request->validate([
            'memberId' => ['required', 'integer', 'min:1'],
            'memberCardId' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'string', 'date_format:H:i'],
            'courseId' => ['sometimes', 'integer', 'min:1'],
            'remark' => ['sometimes', 'nullable', 'string', 'max:150'],
            'acknowledgeGroupOverlap' => ['sometimes', 'boolean'],
            'commandKey' => ['required', 'uuid'],
        ]);

        // 整包事务：resolveBookableSession 会创建（或复用）私教 session，
        // 后续卡校验失败（BOOKING_CARD_NOT_PAYABLE 等）必须整体回滚，
        // 否则孤儿 session 残留导致时间段被占用。
        [$session, $result] = \Illuminate\Support\Facades\DB::transaction(function () use (
            $profiles, $staff, $siteModel, $profileModel, $payload,
            $bookingAccess, $payableCards, $writer,
        ) {
            $session = $profiles->resolveBookableSession($staff, $siteModel, $profileModel, $payload);
            $member = $bookingAccess->member($staff, $siteModel, (int) $payload['memberId']);
            $card = $payableCards->resolvePayableCard($member, $session, (int) $payload['memberCardId']);
            $result = $writer->createForStaff(
                $staff, $member, $session, $card, $payload['commandKey'],
                memberRemark: trim((string) ($payload['remark'] ?? '')) !== '' ? trim((string) $payload['remark']) : null,
            );

            return [$session, $result];
        });

        return ApiResponse::success([
            'appointment' => \App\Services\Booking\AppointmentPresenter::toArray($result['appointment']),
            'sessionId' => $session->id,
        ], $result['created'] ? 201 : 200);
    }

    public function destroy(
        Request $request,
        int $site,
        int $profile,
        StaffCourseCatalogAccessService $access,
        CoachPrivateProfileService $profiles,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'course-catalog.write', $siteModel->id);
        $profileModel = $profiles->find($staff, $siteModel, $profile);
        $profiles->delete($staff, $siteModel, $profileModel);

        return ApiResponse::success(['deleted' => true]);
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
