<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\InteractsWithAdminBusinessScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EndCompensationRoleAssignmentRequest;
use App\Http\Requests\Admin\EndMemberCardShareAssignmentRequest;
use App\Http\Requests\Admin\ReplaceMemberCardShareAssignmentsRequest;
use App\Http\Requests\Admin\SaveCompensationRoleAssignmentRequest;
use App\Http\Requests\Admin\SaveCompensationRoleRequest;
use App\Http\Requests\Admin\SaveMemberCardShareAssignmentRequest;
use App\Models\CompensationRole;
use App\Models\MemberCard;
use App\Models\MemberCardShareAssignment;
use App\Models\Site;
use App\Models\Staff;
use App\Models\StaffCompensationRoleAssignment;
use App\Models\Tenant;
use App\Services\Compensation\CompensationRoleService;
use App\Services\Compensation\MemberCardShareAssignmentService;
use App\Support\ApiResponse;
use App\Support\DomainActor;
use Illuminate\Http\Request;

class AdminCompensationRoleController extends Controller
{
    use InteractsWithAdminBusinessScope;

    public function __construct(
        private readonly CompensationRoleService $roles,
        private readonly MemberCardShareAssignmentService $cardAssignments,
    ) {}

    public function index(Request $request, Tenant $tenant, Site $site)
    {
        $site = $this->scopedSite($tenant, $site);
        $filters = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'in:active,archived'],
            'roleType' => ['sometimes', 'nullable', 'in:delivery,share'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->roles->queryForSite($tenant->id, $site->id, $filters)
            ->when($filters['roleType'] ?? null, fn ($query, $roleType) => $query->where('role_type', $roleType))
            ->withCount(['assignments as active_assignment_count' => fn ($query) => $query->where('status', 'active')])
            ->paginate($filters['perPage'] ?? 20);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (CompensationRole $role) => [
                ...$this->roles->present($role),
                'roleTypeLabel' => $role->role_type === 'delivery' ? 'A · 上课' : 'B · 分成',
                'assignedStaffCount' => (int) $role->active_assignment_count,
                'updatedAt' => $role->updated_at?->toIso8601String(),
            ])->values(),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function store(SaveCompensationRoleRequest $request, Tenant $tenant, Site $site)
    {
        $site = $this->scopedSite($tenant, $site);
        $role = $this->roles->create($this->actor($request), $site, $request->validated());

        return ApiResponse::success($this->roles->present($role), 201);
    }

    public function update(
        SaveCompensationRoleRequest $request,
        Tenant $tenant,
        Site $site,
        CompensationRole $compensationRole,
    ) {
        $site = $this->scopedSite($tenant, $site);
        abort_unless($compensationRole->role_type === $request->validated('roleType'), 422, 'COMPENSATION_ROLE_TYPE_IMMUTABLE');
        $role = $this->roles->update($this->actor($request), $site, $compensationRole, $request->validated());

        return ApiResponse::success($this->roles->present($role));
    }

    public function archive(
        Request $request,
        Tenant $tenant,
        Site $site,
        CompensationRole $compensationRole,
    ) {
        $site = $this->scopedSite($tenant, $site);
        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:2', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ]);
        $role = $this->roles->update($this->actor($request), $site, $compensationRole, [
            'version' => $validated['version'],
            'status' => 'archived',
            'reason' => $validated['reason'],
            'commandKey' => $validated['commandKey'],
        ]);

        return ApiResponse::success($this->roles->present($role));
    }

    public function assignments(Request $request, Tenant $tenant, Site $site)
    {
        $site = $this->scopedSite($tenant, $site);
        $filters = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'in:active,archived'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $paginator = StaffCompensationRoleAssignment::query()
            ->where('tenant_id', $tenant->id)
            ->where('site_id', $site->id)
            ->with(['role', 'staff'])
            ->when($filters['query'] ?? null, fn ($query, $term) => $query->where(function ($nested) use ($term) {
                $nested->whereHas('role', fn ($roles) => $roles->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('staff', fn ($staff) => $staff
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('employee_no', 'like', "%{$term}%"));
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($filters['perPage'] ?? 20);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (StaffCompensationRoleAssignment $assignment) => $this->presentAssignment($assignment))->values(),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function storeAssignment(
        SaveCompensationRoleAssignmentRequest $request,
        Tenant $tenant,
        Site $site,
    ) {
        $site = $this->scopedSite($tenant, $site);
        $validated = $request->validated();
        $target = Staff::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($validated['staffId'])
            ->whereHas('sites', fn ($sites) => $sites->whereKey($site->id))
            ->firstOrFail();
        CompensationRole::query()
            ->where('tenant_id', $tenant->id)
            ->where('site_id', $site->id)
            ->where('status', 'active')
            ->findOrFail($validated['roleId']);

        $assignments = $this->assignmentPayloadForStaff($site, $target)
            ->reject(fn (array $item) => $item['compensationRoleId'] === (int) $validated['roleId'])
            ->push([
                'compensationRoleId' => (int) $validated['roleId'],
                'activeFrom' => $validated['effectiveFrom'] ?? now()->toDateString(),
                'activeUntil' => $validated['effectiveUntil'] ?? null,
            ])->values()->all();
        $saved = $this->roles->assignStaff(
            $this->actor($request),
            $site,
            $target,
            $assignments,
            $validated['commandKey'],
            $validated['reason'] ?? null,
        );
        $assignment = collect($saved)->firstWhere('compensation_role_id', (int) $validated['roleId']);

        return ApiResponse::success($this->presentAssignment($assignment), 201);
    }

    public function endAssignment(
        EndCompensationRoleAssignmentRequest $request,
        Tenant $tenant,
        Site $site,
        StaffCompensationRoleAssignment $assignment,
    ) {
        $site = $this->scopedSite($tenant, $site);
        abort_unless($assignment->tenant_id === $tenant->id && $assignment->site_id === $site->id, 404);
        $validated = $request->validated();
        $updated = $this->roles->endStaffAssignment(
            $this->actor($request),
            $site,
            $assignment,
            (int) $validated['version'],
            $validated['effectiveUntil'],
            $validated['reason'],
            $validated['commandKey'],
        );

        return ApiResponse::success($this->presentAssignment($updated));
    }

    public function shareAssignments(Request $request, Tenant $tenant, Site $site)
    {
        $site = $this->scopedSite($tenant, $site);
        $filters = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'in:active,archived'],
            'memberCardId' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $scopeVersion = null;
        if (($filters['memberCardId'] ?? null) !== null) {
            $scopeVersion = (int) MemberCard::query()
                ->where('tenant_id', $tenant->id)
                ->where('site_id', $site->id)
                ->findOrFail($filters['memberCardId'])
                ->share_assignment_version;
        }
        $paginator = MemberCardShareAssignment::query()
            ->where('member_card_share_assignments.tenant_id', $tenant->id)
            ->where('member_card_share_assignments.site_id', $site->id)
            ->join('member_cards', 'member_cards.id', '=', 'member_card_share_assignments.member_card_id')
            ->join('members', 'members.id', '=', 'member_cards.member_id')
            ->leftJoin('member_crm_profiles', 'member_crm_profiles.member_id', '=', 'members.id')
            ->leftJoin('accounts', 'accounts.id', '=', 'members.account_id')
            ->join('staff', 'staff.id', '=', 'member_card_share_assignments.staff_id')
            ->join('compensation_roles', 'compensation_roles.id', '=', 'member_card_share_assignments.compensation_role_id')
            ->when($filters['memberCardId'] ?? null, fn ($query, $memberCardId) => $query
                ->where('member_card_share_assignments.member_card_id', $memberCardId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('member_card_share_assignments.status', $status))
            ->when($filters['query'] ?? null, fn ($query, $term) => $query->where(function ($nested) use ($term) {
                $nested->where('member_cards.card_no', 'like', "%{$term}%")
                    ->orWhere('members.member_no', 'like', "%{$term}%")
                    ->orWhere('member_crm_profiles.name', 'like', "%{$term}%")
                    ->orWhere('accounts.display_name', 'like', "%{$term}%")
                    ->orWhere('staff.name', 'like', "%{$term}%")
                    ->orWhere('compensation_roles.name', 'like', "%{$term}%");
            }))
            ->select([
                'member_card_share_assignments.*', 'member_cards.card_no', 'member_cards.share_assignment_version', 'members.member_no',
                'member_crm_profiles.name as profile_name', 'accounts.display_name as account_name',
                'staff.name as staff_name', 'compensation_roles.name as role_name',
            ])
            ->orderByDesc('member_card_share_assignments.id')
            ->paginate($filters['perPage'] ?? 20);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (MemberCardShareAssignment $item) => [
                'id' => $item->id,
                'memberCardId' => $item->member_card_id,
                'memberCardNo' => $item->card_no,
                'memberName' => $item->profile_name ?? $item->account_name ?? $item->member_no,
                'staffId' => $item->staff_id,
                'staffName' => $item->staff_name,
                'roleId' => $item->compensation_role_id,
                'roleName' => $item->role_name,
                'allocationBps' => $item->allocation_bps,
                'effectiveFrom' => $item->effective_from?->toDateString(),
                'effectiveUntil' => $item->effective_until?->toDateString(),
                'version' => $item->version,
                'scopeVersion' => (int) $item->share_assignment_version,
                'status' => $item->status,
            ])->values(),
            'pagination' => $this->pagination($paginator),
            'scopeVersion' => $scopeVersion,
        ]);
    }

    public function storeShareAssignment(
        SaveMemberCardShareAssignmentRequest $request,
        Tenant $tenant,
        Site $site,
    ) {
        $site = $this->scopedSite($tenant, $site);
        $validated = $request->validated();
        $card = MemberCard::query()
            ->where('tenant_id', $tenant->id)
            ->where('site_id', $site->id)
            ->findOrFail($validated['memberCardId']);
        $assignments = $this->activeCardAssignmentPayload($card)
            ->reject(fn (array $item) => $item['staffId'] === (int) $validated['staffId']
                && $item['compensationRoleId'] === (int) $validated['compensationRoleId'])
            ->push([
                'staffId' => (int) $validated['staffId'],
                'compensationRoleId' => (int) $validated['compensationRoleId'],
                'allocationBps' => (int) $validated['allocationBps'],
                'effectiveFrom' => $validated['effectiveFrom'] ?? now()->toDateString(),
                'effectiveUntil' => $validated['effectiveUntil'] ?? null,
            ])->values()->all();
        $saved = $this->cardAssignments->replace(
            $card,
            $site,
            $assignments,
            $this->actor($request),
            $validated['commandKey'],
            $validated['reason'],
            (int) $validated['expectedVersion'],
        );

        return ApiResponse::success(['items' => collect($saved)->map(fn ($item) => $this->presentShareAssignment($item))->values()], 201);
    }

    public function endShareAssignment(
        EndMemberCardShareAssignmentRequest $request,
        Tenant $tenant,
        Site $site,
        MemberCardShareAssignment $assignment,
    ) {
        $site = $this->scopedSite($tenant, $site);
        abort_unless($assignment->tenant_id === $tenant->id && $assignment->site_id === $site->id, 404);
        abort_unless($assignment->status === 'active', 409, 'SHARE_ASSIGNMENT_ALREADY_ENDED');
        $card = MemberCard::query()
            ->where('tenant_id', $tenant->id)
            ->where('site_id', $site->id)
            ->findOrFail($assignment->member_card_id);
        $payload = $this->activeCardAssignmentPayload($card)
            ->reject(fn (array $item) => $item['id'] === $assignment->id)
            ->map(fn (array $item) => collect($item)->except('id')->all())
            ->values()->all();
        $this->cardAssignments->replace(
            $card,
            $site,
            $payload,
            $this->actor($request),
            $request->validated('commandKey'),
            $request->validated('reason'),
            (int) $request->validated('expectedVersion'),
        );

        return ApiResponse::success(['ended' => true, 'assignmentId' => $assignment->id]);
    }

    public function replaceShareAssignments(
        ReplaceMemberCardShareAssignmentsRequest $request,
        Tenant $tenant,
        Site $site,
        MemberCard $memberCard,
    ) {
        $site = $this->scopedSite($tenant, $site);
        abort_unless($memberCard->tenant_id === $tenant->id && $memberCard->site_id === $site->id, 404);
        $validated = $request->validated();
        $saved = $this->cardAssignments->replace(
            $memberCard,
            $site,
            $validated['assignments'],
            $this->actor($request),
            $validated['commandKey'],
            $validated['reason'],
            (int) $validated['expectedVersion'],
        );
        $scopeVersion = (int) $memberCard->fresh()->share_assignment_version;

        return ApiResponse::success([
            'memberCardId' => $memberCard->id,
            'scopeVersion' => $scopeVersion,
            'items' => collect($saved)->map(fn ($item) => $this->presentShareAssignment($item))->values(),
        ]);
    }

    private function actor(Request $request): DomainActor
    {
        return DomainActor::superAdmin($request->user());
    }

    private function assignmentPayloadForStaff(Site $site, Staff $target)
    {
        return StaffCompensationRoleAssignment::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->where('staff_id', $target->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->map(fn (StaffCompensationRoleAssignment $assignment) => [
                'compensationRoleId' => $assignment->compensation_role_id,
                'activeFrom' => $assignment->active_from?->toDateString(),
                'activeUntil' => $assignment->active_until?->toDateString(),
            ]);
    }

    private function activeCardAssignmentPayload(MemberCard $card)
    {
        return MemberCardShareAssignment::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('member_card_id', $card->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->map(fn (MemberCardShareAssignment $assignment) => [
                'id' => $assignment->id,
                'staffId' => $assignment->staff_id,
                'compensationRoleId' => $assignment->compensation_role_id,
                'allocationBps' => $assignment->allocation_bps,
                'effectiveFrom' => $assignment->effective_from?->toDateString(),
                'effectiveUntil' => $assignment->effective_until?->toDateString(),
            ]);
    }

    private function presentAssignment(StaffCompensationRoleAssignment $assignment): array
    {
        $assignment->loadMissing(['role', 'staff']);

        return [
            'id' => $assignment->id,
            'roleId' => $assignment->compensation_role_id,
            'roleName' => $assignment->role?->name,
            'roleType' => $assignment->role?->role_type,
            'staffId' => $assignment->staff_id,
            'staffName' => $assignment->staff?->name,
            'employeeNo' => $assignment->staff?->employee_no,
            'effectiveFrom' => $assignment->active_from?->toDateString(),
            'effectiveUntil' => $assignment->active_until?->toDateString(),
            'status' => $assignment->status,
            'version' => $this->assignmentVersion($assignment),
        ];
    }

    private function assignmentVersion(StaffCompensationRoleAssignment $assignment): int
    {
        return max(1, (int) $assignment->version);
    }

    private function presentShareAssignment(MemberCardShareAssignment $assignment): array
    {
        $assignment->loadMissing(['role', 'staff']);

        return [
            'id' => $assignment->id,
            'memberCardId' => $assignment->member_card_id,
            'staffId' => $assignment->staff_id,
            'staffName' => $assignment->staff?->name,
            'roleId' => $assignment->compensation_role_id,
            'roleName' => $assignment->role?->name,
            'allocationBps' => $assignment->allocation_bps,
            'effectiveFrom' => $assignment->effective_from?->toDateString(),
            'effectiveUntil' => $assignment->effective_until?->toDateString(),
            'version' => $assignment->version,
            'scopeVersion' => (int) MemberCard::query()
                ->where('tenant_id', $assignment->tenant_id)
                ->whereKey($assignment->member_card_id)
                ->value('share_assignment_version'),
            'status' => $assignment->status,
        ];
    }
}
