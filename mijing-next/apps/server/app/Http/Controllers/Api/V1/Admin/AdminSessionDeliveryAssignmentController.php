<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\InteractsWithAdminBusinessScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSessionDeliveryAssignmentsRequest;
use App\Models\ScheduleSession;
use App\Models\ScheduleSessionStaffAssignment;
use App\Models\Site;
use App\Models\Tenant;
use App\Services\Compensation\ScheduleSessionDeliveryAssignmentService;
use App\Support\ApiResponse;
use App\Support\DomainActor;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminSessionDeliveryAssignmentController extends Controller
{
    use InteractsWithAdminBusinessScope;

    public function __construct(private readonly ScheduleSessionDeliveryAssignmentService $assignments) {}

    public function index(Request $request, Tenant $tenant, Site $site)
    {
        $site = $this->scopedSite($tenant, $site);
        $filters = $request->validate([
            'sessionId' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $paginator = ScheduleSessionStaffAssignment::query()
            ->where('schedule_session_staff_assignments.tenant_id', $tenant->id)
            ->where('schedule_session_staff_assignments.site_id', $site->id)
            ->join('schedule_sessions', 'schedule_sessions.id', '=', 'schedule_session_staff_assignments.schedule_session_id')
            ->join('courses', 'courses.id', '=', 'schedule_sessions.course_id')
            ->join('staff', 'staff.id', '=', 'schedule_session_staff_assignments.staff_id')
            ->join('compensation_roles', 'compensation_roles.id', '=', 'schedule_session_staff_assignments.compensation_role_id')
            ->when($filters['sessionId'] ?? null, fn ($query, $sessionId) => $query
                ->where('schedule_session_staff_assignments.schedule_session_id', $sessionId))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('schedule_sessions.starts_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('schedule_sessions.starts_at', '<=', $to))
            ->when($filters['query'] ?? null, fn ($query, $term) => $query->where(function ($nested) use ($term) {
                $nested->where('courses.name', 'like', "%{$term}%")
                    ->orWhere('staff.name', 'like', "%{$term}%")
                    ->orWhere('compensation_roles.name', 'like', "%{$term}%");
            }))
            ->select([
                'schedule_session_staff_assignments.*',
                'schedule_sessions.version as session_version',
                'schedule_sessions.starts_at',
                'courses.name as course_name',
                'staff.name as staff_name',
                'compensation_roles.name as role_name',
            ])
            ->orderByDesc('schedule_sessions.starts_at')
            ->orderByDesc('schedule_session_staff_assignments.is_primary')
            ->orderBy('schedule_session_staff_assignments.id')
            ->paginate($filters['perPage'] ?? 20);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (ScheduleSessionStaffAssignment $assignment) => [
                'id' => $assignment->id,
                'sessionId' => $assignment->schedule_session_id,
                'sessionLabel' => $assignment->course_name.' · '.Carbon::parse($assignment->starts_at)->format('Y-m-d H:i'),
                'courseName' => $assignment->course_name,
                'startsAt' => Carbon::parse($assignment->starts_at)->toIso8601String(),
                'sessionVersion' => (int) $assignment->session_version,
                'staffId' => $assignment->staff_id,
                'staffName' => $assignment->staff_name,
                'roleId' => $assignment->compensation_role_id,
                'roleName' => $assignment->role_name,
                'allocationBps' => $assignment->allocation_bps,
                'isPrimary' => $assignment->is_primary,
                'assignmentVersion' => $assignment->assignment_version,
                'updatedAt' => $assignment->updated_at?->toIso8601String(),
            ])->values(),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function replace(
        SaveSessionDeliveryAssignmentsRequest $request,
        Tenant $tenant,
        Site $site,
        ScheduleSession $session,
    ) {
        $site = $this->scopedSite($tenant, $site);
        abort_unless($session->tenant_id === $tenant->id && $session->site_id === $site->id, 404);
        $validated = $request->validated();
        $saved = $this->assignments->replace(
            DomainActor::superAdmin($request->user()),
            $site,
            $session,
            $validated['assignments'],
            $validated['commandKey'],
            $validated['expectedVersion'] ?? null,
            $validated['reason'],
        );

        return ApiResponse::success([
            'sessionId' => $session->id,
            'sessionVersion' => $session->fresh()->version,
            'items' => collect($saved)->map(fn (ScheduleSessionStaffAssignment $assignment) => $this->assignments->present($assignment))->values(),
        ]);
    }
}
