<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReplaceScheduleSessionDeliveryAssignmentsRequest;
use App\Http\Requests\StoreScheduleSessionRequest;
use App\Http\Requests\UpdateScheduleSessionRequest;
use App\Models\CompensationRole;
use App\Models\ScheduleSession;
use App\Models\Staff;
use App\Models\StaffCompensationRoleAssignment;
use App\Services\Compensation\ScheduleSessionDeliveryAssignmentService;
use App\Services\Schedule\ScheduleSessionWriteService;
use App\Services\Schedule\StaffScheduleSessionAccessService;
use App\Support\ApiResponse;
use App\Support\DomainActor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            ->with(['course', 'room', 'coach', 'deliveryAssignments.staff', 'deliveryAssignments.role'])
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
        $sessionModel = $access->session($staff, $siteModel, $session)
            ->load(['course', 'room', 'coach', 'deliveryAssignments.staff', 'deliveryAssignments.role']);

        return ApiResponse::success($this->sessionData($sessionModel));
    }

    public function replaceDeliveryAssignments(
        ReplaceScheduleSessionDeliveryAssignmentsRequest $request,
        int $site,
        int $session,
        StaffScheduleSessionAccessService $access,
        ScheduleSessionDeliveryAssignmentService $assignments,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.write', $siteModel->id);
        $sessionModel = $access->session($staff, $siteModel, $session);
        $validated = $request->validated();
        $saved = $assignments->replace(
            DomainActor::staff($staff),
            $siteModel,
            $sessionModel,
            $validated['assignments'],
            $validated['commandKey'],
            isset($validated['expectedVersion']) ? (int) $validated['expectedVersion'] : null,
        );

        return ApiResponse::success([
            'sessionId' => $sessionModel->id,
            'version' => $sessionModel->fresh()->version,
            'assignments' => collect($saved)->map(fn ($item) => $assignments->present($item))->values(),
        ]);
    }

    public function store(
        StoreScheduleSessionRequest $request,
        int $site,
        StaffScheduleSessionAccessService $access,
        ScheduleSessionWriteService $writer,
        ScheduleSessionDeliveryAssignmentService $assignments,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.write', $siteModel->id);
        $payload = $request->validated();
        if (! isset($payload['deliveryAssignments'])) {
            $deliveryRoles = CompensationRole::query()
                ->where('tenant_id', $staff->tenant_id)->where('site_id', $siteModel->id)
                ->where('role_type', 'delivery')->where('status', 'active')->get();
            if ($deliveryRoles->isNotEmpty()) {
                // Compatibility bridge for old clients: a unique, already assigned
                // delivery role can be snapshotted from coachStaffId atomically.
                abort_unless($deliveryRoles->count() === 1, 422, 'SESSION_DELIVERY_ASSIGNMENTS_REQUIRED');
                $role = $deliveryRoles->first();
                $businessDate = \Carbon\Carbon::parse($payload['startsAt'])
                    ->timezone($siteModel->timezone ?: config('app.timezone'))->toDateString();
                $ownsRole = StaffCompensationRoleAssignment::query()
                    ->where('tenant_id', $staff->tenant_id)->where('site_id', $siteModel->id)
                    ->where('staff_id', (int) $payload['coachStaffId'])
                    ->where('compensation_role_id', $role->id)
                    ->whereIn('status', ['active', 'archived'])
                    ->where(fn ($query) => $query->whereNull('active_from')->orWhere('active_from', '<=', $businessDate))
                    ->where(fn ($query) => $query->whereNull('active_until')->orWhere('active_until', '>=', $businessDate))
                    ->exists();
                abort_unless($ownsRole, 422, 'SESSION_DELIVERY_ASSIGNMENTS_REQUIRED');
                $payload['deliveryAssignments'] = [[
                    'staffId' => (int) $payload['coachStaffId'],
                    'compensationRoleId' => $role->id,
                    'allocationBps' => 10000,
                    'isPrimary' => true,
                ]];
                $payload['assignmentCommandKey'] = (string) Str::uuid();
            }
        }
        $session = DB::transaction(function () use ($staff, $siteModel, $writer, $assignments, $payload) {
            $created = $writer->create($staff, $siteModel, $payload);
            if (isset($payload['deliveryAssignments'])) {
                $assignments->replace(
                    DomainActor::staff($staff), $siteModel, $created,
                    $payload['deliveryAssignments'], $payload['assignmentCommandKey'], $created->version,
                );
            }

            return $created->fresh()->load(['course', 'room', 'coach', 'deliveryAssignments.staff', 'deliveryAssignments.role']);
        });

        return ApiResponse::success($this->sessionData($session), 201);
    }

    public function update(
        UpdateScheduleSessionRequest $request,
        int $site,
        int $session,
        StaffScheduleSessionAccessService $access,
        ScheduleSessionWriteService $writer,
        ScheduleSessionDeliveryAssignmentService $assignments,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'schedule.session.write', $siteModel->id);
        $sessionModel = $access->session($staff, $siteModel, $session);
        $payload = $request->validated();
        $sessionModel = DB::transaction(function () use ($staff, $siteModel, $sessionModel, $writer, $assignments, $payload) {
            $updated = $writer->update($sessionModel, $payload);
            if (isset($payload['deliveryAssignments'])) {
                $assignments->replace(
                    DomainActor::staff($staff), $siteModel, $updated,
                    $payload['deliveryAssignments'], $payload['assignmentCommandKey'], $updated->version,
                );
            }

            return $updated->fresh()->load(['course', 'room', 'coach', 'deliveryAssignments.staff', 'deliveryAssignments.role']);
        });

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
            'deliveryAssignments' => $session->relationLoaded('deliveryAssignments')
                ? $session->deliveryAssignments->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'staffId' => $assignment->staff_id,
                    'staffName' => $assignment->staff?->name,
                    'compensationRoleId' => $assignment->compensation_role_id,
                    'roleName' => $assignment->role?->name,
                    'allocationBps' => $assignment->allocation_bps,
                    'isPrimary' => $assignment->is_primary,
                    'assignmentVersion' => $assignment->assignment_version,
                ])->values()
                : [],
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
