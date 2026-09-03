<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CompensationRole;
use App\Models\Staff;
use App\Models\StaffCompensationRoleAssignment;
use App\Services\Cards\StaffCardProductAccessService;
use App\Services\Compensation\CompensationRoleService;
use App\Support\ApiResponse;
use App\Support\DomainActor;
use Illuminate\Http\Request;

class StaffCompensationRoleController extends Controller
{
    public function index(Request $request, int $site, StaffCardProductAccessService $access, CompensationRoleService $roles)
    {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'compensation.role.read');
        $filters = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'in:active,archived'],
            'roleType' => ['sometimes', 'nullable', 'in:delivery,share'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $paginator = $roles->queryForSite($staff->tenant_id, $siteModel->id, $filters)
            ->when($filters['roleType'] ?? null, fn ($query, $type) => $query->where('role_type', $type))
            ->paginate($filters['perPage'] ?? 20);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (CompensationRole $role) => $roles->present($role))->values(),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function store(Request $request, int $site, StaffCardProductAccessService $access, CompensationRoleService $roles)
    {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'compensation.role.write');
        $payload = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'roleType' => ['required', 'in:delivery,share'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ]);
        $role = $roles->create(DomainActor::staff($staff), $siteModel, $payload);

        return ApiResponse::success($roles->present($role), 201);
    }

    public function update(Request $request, int $site, int $compensationRole, StaffCardProductAccessService $access, CompensationRoleService $roles)
    {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'compensation.role.write');
        $role = CompensationRole::query()
            ->where('tenant_id', $staff->tenant_id)->where('site_id', $siteModel->id)->findOrFail($compensationRole);
        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'code' => ['sometimes', 'string', 'max:80'],
            'roleType' => ['sometimes', 'in:delivery,share'],
            'status' => ['sometimes', 'in:active,archived'],
            'version' => ['required', 'integer', 'min:1'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ]);

        return ApiResponse::success($roles->present(
            $roles->update(DomainActor::staff($staff), $siteModel, $role, $payload),
        ));
    }

    public function archive(Request $request, int $site, int $compensationRole, StaffCardProductAccessService $access, CompensationRoleService $roles)
    {
        $request->merge(['status' => 'archived']);

        return $this->update($request, $site, $compensationRole, $access, $roles);
    }

    public function assignments(Request $request, int $site, int $staffMember, StaffCardProductAccessService $access)
    {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'compensation.role.read');
        $target = $this->targetStaff($staff, $siteModel->id, $staffMember);
        $today = now()->timezone($siteModel->timezone ?: config('app.timezone'))->toDateString();
        $items = StaffCompensationRoleAssignment::query()
            ->where('tenant_id', $staff->tenant_id)->where('site_id', $siteModel->id)
            ->where('staff_id', $target->id)->whereIn('status', ['active', 'archived'])
            ->where(fn ($query) => $query->where('status', 'active')->orWhere('active_until', '>=', $today))
            ->with(['role', 'staff'])->orderBy('id')->get();

        return ApiResponse::success(['items' => $items->map(fn ($item) => $this->presentAssignment($item, $today))->values()]);
    }

    public function replaceAssignments(
        Request $request,
        int $site,
        int $staffMember,
        StaffCardProductAccessService $access,
        CompensationRoleService $roles,
    ) {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'compensation.role.write');
        $target = $this->targetStaff($staff, $siteModel->id, $staffMember);
        $payload = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*.compensationRoleId' => ['required', 'integer', 'min:1', 'distinct'],
            'assignments.*.activeFrom' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'assignments.*.activeUntil' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:2', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ]);
        $saved = $roles->assignStaff(
            DomainActor::staff($staff),
            $siteModel,
            $target,
            $payload['assignments'],
            $payload['commandKey'] ?? null,
            $payload['reason'] ?? null,
        );

        $today = now()->timezone($siteModel->timezone ?: config('app.timezone'))->toDateString();

        return ApiResponse::success(['items' => collect($saved)->map(fn ($item) => $this->presentAssignment($item, $today))->values()]);
    }

    private function context(Request $request, int $site, StaffCardProductAccessService $access, string $permission): array
    {
        /** @var Staff $staff */
        $staff = $request->attributes->get('staff_context');
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, $permission, $siteModel->id);

        return [$staff, $siteModel];
    }

    private function targetStaff(Staff $actor, int $siteId, int $staffId): Staff
    {
        return Staff::query()->where('tenant_id', $actor->tenant_id)->whereKey($staffId)
            ->whereHas('sites', fn ($query) => $query->whereKey($siteId))->firstOrFail();
    }

    private function presentAssignment(StaffCompensationRoleAssignment $assignment, ?string $today = null): array
    {
        $assignment->loadMissing(['role', 'staff']);

        $today ??= now()->toDateString();
        $from = $assignment->active_from?->toDateString();
        $until = $assignment->active_until?->toDateString();

        return [
            'id' => $assignment->id,
            'staffId' => $assignment->staff_id,
            'staffName' => $assignment->staff?->name,
            'roleId' => $assignment->compensation_role_id,
            'roleName' => $assignment->role?->name,
            'roleType' => $assignment->role?->role_type,
            'effectiveFrom' => $from,
            'effectiveUntil' => $until,
            'effectiveState' => $from !== null && $from > $today
                ? 'scheduled'
                : (($until === null || $until >= $today) ? 'current' : 'expired'),
            'status' => $assignment->status,
            'version' => $assignment->version,
        ];
    }

    private function pagination($paginator): array
    {
        return [
            'page' => $paginator->currentPage(), 'perPage' => $paginator->perPage(),
            'total' => $paginator->total(), 'lastPage' => $paginator->lastPage(),
        ];
    }
}
