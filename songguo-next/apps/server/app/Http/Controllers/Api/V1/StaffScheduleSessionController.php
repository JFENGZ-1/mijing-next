<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleSessionRequest;
use App\Http\Requests\UpdateScheduleSessionRequest;
use App\Models\ScheduleSession;
use App\Models\Staff;
use App\Services\Schedule\ScheduleSessionWriteService;
use App\Services\Schedule\StaffScheduleSessionAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffScheduleSessionController extends Controller
{
    public function index(Request $request, int $site, StaffScheduleSessionAccessService $access)
    {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.read', $siteModel->id);

        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $items = $access->sessionQuery($staff, $siteModel)
            ->where('starts_at', '>=', $request->input('from'))
            ->where('starts_at', '<', $request->input('to'))
            ->with(['course', 'room', 'coach'])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->map(fn (ScheduleSession $session) => $this->sessionData($session));

        return ApiResponse::success(['items' => $items]);
    }

    public function show(Request $request, int $site, int $session, StaffScheduleSessionAccessService $access)
    {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.read', $siteModel->id);
        $sessionModel = $access->session($staff, $siteModel, $session)->load(['course', 'room', 'coach']);

        return ApiResponse::success($this->sessionData($sessionModel));
    }

    public function store(
        StoreScheduleSessionRequest $request,
        int $site,
        StaffScheduleSessionAccessService $access,
        ScheduleSessionWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.write', $siteModel->id);
        $session = $writer->create($staff, $siteModel, $request->validated());

        return ApiResponse::success($this->sessionData($session), 201);
    }

    public function update(
        UpdateScheduleSessionRequest $request,
        int $site,
        int $session,
        StaffScheduleSessionAccessService $access,
        ScheduleSessionWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.write', $siteModel->id);
        $sessionModel = $access->session($staff, $siteModel, $session);
        $sessionModel = $writer->update($sessionModel, $request->validated());

        return ApiResponse::success($this->sessionData($sessionModel));
    }

    public function suspend(
        Request $request,
        int $site,
        int $session,
        StaffScheduleSessionAccessService $access,
        ScheduleSessionWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.write', $siteModel->id);
        $sessionModel = $access->session($staff, $siteModel, $session);
        $sessionModel = $writer->suspend($sessionModel, $staff, cascadeCancelAppointments: true);

        return ApiResponse::success($this->sessionData($sessionModel));
    }

    public function cancel(
        Request $request,
        int $site,
        int $session,
        StaffScheduleSessionAccessService $access,
        ScheduleSessionWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.write', $siteModel->id);
        $sessionModel = $access->session($staff, $siteModel, $session);
        $sessionModel = $writer->cancel($sessionModel);

        return ApiResponse::success($this->sessionData($sessionModel));
    }

    public function unsuspend(
        Request $request,
        int $site,
        int $session,
        StaffScheduleSessionAccessService $access,
        ScheduleSessionWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.write', $siteModel->id);
        $sessionModel = $access->session($staff, $siteModel, $session);
        $sessionModel = $writer->unsuspend($sessionModel);

        return ApiResponse::success($this->sessionData($sessionModel));
    }

    private function sessionData(ScheduleSession $session): array
    {
        return [
            'id' => $session->id,
            'courseId' => $session->course_id,
            'courseName' => $session->course?->name,
            'roomId' => $session->room_id,
            'roomName' => $session->room?->name,
            'coachStaffId' => $session->coach_staff_id,
            'coachName' => $session->coach?->name,
            'startsAt' => $session->starts_at?->toIso8601String(),
            'endsAt' => $session->ends_at?->toIso8601String(),
            'capacity' => $session->capacity,
            'bookedCount' => $session->booked_count,
            'status' => $session->status->value,
            'sessionKind' => $session->session_kind->value,
            'version' => $session->version,
            'courseType' => $session->course?->course_type?->value,
            'courseFaceStyle' => $session->course?->face_style,
            'courseFaceGradient' => $session->course?->face_style !== null
                ? app(\App\Services\Cards\CardFaceLibraryService::class)->gradientFor($session->course->face_style)
                : null,
            'displayColor' => $session->display_color ?: $session->course?->display_color,
            'courseDisplayColor' => $session->course?->display_color,
            'createdAt' => $session->created_at?->toIso8601String(),
            'updatedAt' => $session->updated_at?->toIso8601String(),
        ];
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
