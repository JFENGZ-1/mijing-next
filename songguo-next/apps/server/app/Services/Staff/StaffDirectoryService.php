<?php

namespace App\Services\Staff;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Account;
use App\Services\Staff\StaffInviteTokenService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffDirectoryService
{
    public function __construct(
        private readonly StaffDeparturePreflightService $preflight,
        private readonly StaffInviteTokenService $inviteTokens,
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>, activeCount: int, departedCount: int}
     */
    public function list(Staff $actor, Site $site): array
    {
        $staffMembers = Staff::query()
            ->with(['account.wechatIdentities', 'roles'])
            ->where('staff.tenant_id', $actor->tenant_id)
            ->whereHas('sites', fn (Builder $sites) => $sites
                ->whereKey($site->id)
                ->where('site_staff.tenant_id', $actor->tenant_id))
            ->orderByRaw("CASE WHEN staff.status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('staff.name')
            ->get();

        $items = $staffMembers
            ->map(fn (Staff $member) => $this->presentListItem($member, $site))
            ->values()
            ->all();

        return [
            'items' => $items,
            'activeCount' => $staffMembers->where('status', 'active')->count(),
            'departedCount' => $staffMembers->where('status', 'departed')->count(),
        ];
    }

    public function show(Staff $actor, Site $site, Staff $member): array
    {
        $member->loadMissing(['account.wechatIdentities', 'roles.permissions']);

        return $this->presentDetail($member, $site, includePreflight: true);
    }

    /**
     * @param  array{displayName: string, mobile?: string|null, gender?: string|null, capabilities?: list<string>, roleId: int}  $payload
     */
    public function create(Staff $actor, Site $site, array $payload): array
    {
        $role = $this->resolveRole($actor, $payload['roleId']);

        return DB::transaction(function () use ($actor, $site, $payload, $role) {
            $account = Account::create([
                'display_name' => $payload['displayName'],
                'mobile' => $payload['mobile'] ?? null,
                'status' => 'active',
            ]);

            $staff = Staff::create([
                'tenant_id' => $actor->tenant_id,
                'account_id' => $account->id,
                'employee_no' => $this->nextEmployeeNo($actor->tenant_id),
                'name' => $payload['displayName'],
                'gender' => $payload['gender'] ?? null,
                'status' => 'active',
                'joined_on' => today(),
            ]);

            $staff->sites()->attach($site->id, [
                'tenant_id' => $actor->tenant_id,
                'is_primary' => true,
                'capabilities' => json_encode($payload['capabilities'] ?? [], JSON_THROW_ON_ERROR),
            ]);
            $staff->roles()->attach($role->id, [
                'tenant_id' => $actor->tenant_id,
                'site_id' => $site->id,
            ]);

            if ($site->owner_staff_id === null) {
                $site->update(['owner_staff_id' => $staff->id]);
            }

            return $this->presentDetail($staff->fresh(['account.wechatIdentities', 'roles']), $site->fresh());
        });
    }

    /**
     * @param  array{displayName?: string, mobile?: string|null, gender?: string|null, capabilities?: list<string>, roleId?: int, version?: int}  $payload
     */
    public function update(Staff $actor, Site $site, Staff $member, array $payload): array
    {
        if (array_key_exists('version', $payload) && (int) $payload['version'] !== $member->version) {
            abort(409, 'VERSION_CONFLICT');
        }

        return DB::transaction(function () use ($actor, $site, $member, $payload) {
            $member->loadMissing('account');
            $account = $member->account;
            $staffUpdates = [];
            $accountUpdates = [];

            if (array_key_exists('displayName', $payload)) {
                $staffUpdates['name'] = $payload['displayName'];
                $accountUpdates['display_name'] = $payload['displayName'];
            }
            if (array_key_exists('gender', $payload)) {
                $staffUpdates['gender'] = $payload['gender'];
            }
            if (array_key_exists('mobile', $payload)) {
                $accountUpdates['mobile'] = $payload['mobile'];
            }

            if ($staffUpdates !== [] || $accountUpdates !== []) {
                $staffUpdates['version'] = $member->version + 1;
                $member->update($staffUpdates);
                if ($accountUpdates !== []) {
                    $account->update($accountUpdates);
                }
            }

            if (array_key_exists('capabilities', $payload)) {
                $member->sites()->updateExistingPivot($site->id, [
                    'capabilities' => json_encode($payload['capabilities'] ?? [], JSON_THROW_ON_ERROR),
                ]);
            }

            if (array_key_exists('roleId', $payload)) {
                $role = $this->resolveRole($actor, $payload['roleId']);
                $member->roles()
                    ->wherePivot('site_id', $site->id)
                    ->detach();
                $member->roles()->attach($role->id, [
                    'tenant_id' => $actor->tenant_id,
                    'site_id' => $site->id,
                ]);
            }

            return $this->presentDetail($member->fresh(['account.wechatIdentities', 'roles']), $site);
        });
    }

    public function softDeparture(Staff $actor, Site $site, Staff $member): array
    {
        abort_if($member->id === $actor->id, 422, 'STAFF_SELF_DEPARTURE_FORBIDDEN');
        abort_if($site->owner_staff_id === $member->id, 422, 'SITE_OWNER_DEPARTURE_FORBIDDEN');

        if ($this->preflight->hasFutureBookings($member, $site)) {
            abort(409, 'STAFF_DEPARTURE_BLOCKED');
        }

        $member->update([
            'status' => 'departed',
            'left_on' => today(),
            'version' => $member->version + 1,
        ]);

        return $this->presentDetail($member->fresh(['account.wechatIdentities', 'roles']), $site);
    }

    public function transferOwnership(Staff $actor, Site $site, Staff $member): array
    {
        abort_if($site->owner_staff_id === $member->id, 422, 'SITE_OWNER_ALREADY_ASSIGNED');
        abort_unless($member->status === 'active', 422, 'STAFF_TRANSFER_INACTIVE');
        abort_unless(
            $member->account?->wechatIdentities()->exists() ?? false,
            422,
            'STAFF_TRANSFER_NOT_INVITED',
        );

        $site->update(['owner_staff_id' => $member->id, 'version' => $site->version + 1]);

        return [
            'siteId' => $site->id,
            'ownerStaffId' => $member->id,
            'ownerDisplayName' => $member->name,
            'version' => $site->fresh()->version,
        ];
    }

    /**
     * @return list<array{id: int, name: string, code: string, isSystem: bool, permissionCount: int}>
     */
    public function roleOptions(Staff $actor): array
    {
        return Role::query()
            ->where('tenant_id', $actor->tenant_id)
            ->where('status', 'active')
            ->withCount('permissions')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'code' => $role->code,
                'isSystem' => (bool) $role->is_system,
                'permissionCount' => $role->permissions_count,
            ])
            ->all();
    }

    /**
     * @return list<array{module: string, permissions: list<array{id: int, code: string, name: string}>}>
     */
    public function permissionCatalog(): array
    {
        return Permission::query()
            ->orderBy('module')
            ->orderBy('code')
            ->get()
            ->groupBy('module')
            ->map(fn ($permissions, $module) => [
                'module' => (string) $module,
                'permissions' => $permissions->map(fn (Permission $permission) => [
                    'id' => $permission->id,
                    'code' => $permission->code,
                    'name' => $permission->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function resolveRole(Staff $actor, int $roleId): Role
    {
        return Role::query()
            ->whereKey($roleId)
            ->where('tenant_id', $actor->tenant_id)
            ->where('status', 'active')
            ->firstOrFail();
    }

    private function nextEmployeeNo(int $tenantId): string
    {
        do {
            $candidate = 'EMP'.Str::upper(Str::random(6));
        } while (Staff::query()->where('tenant_id', $tenantId)->where('employee_no', $candidate)->exists());

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentListItem(Staff $member, Site $site): array
    {
        $detail = $this->presentDetail($member, $site, includePreflight: false);

        return [
            'id' => $detail['id'],
            'displayName' => $detail['displayName'],
            'employeeNo' => $detail['employeeNo'],
            'gender' => $detail['gender'],
            'avatarUrl' => $detail['avatarUrl'],
            'status' => $detail['status'],
            'role' => $detail['role'],
            'isSiteOwner' => $detail['isSiteOwner'],
            'hasWechatBinding' => $detail['hasWechatBinding'],
            'capabilities' => $detail['capabilities'],
            'version' => $detail['version'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDetail(Staff $member, Site $site, bool $includePreflight = false): array
    {
        $member->loadMissing(['account.wechatIdentities', 'roles', 'sites']);
        $account = $member->account;
        $mobile = $account?->mobile;
        $sitePivot = $member->sites->firstWhere('id', $site->id)?->pivot;
        $capabilities = $this->normalizeCapabilities($sitePivot?->capabilities);
        $role = $member->roles
            ->first(fn (Role $role) => (int) $role->pivot->site_id === $site->id)
            ?? $member->roles->first(fn (Role $role) => $role->pivot->site_id === null);

        return [
            'id' => $member->id,
            'displayName' => $member->name,
            'employeeNo' => $member->employee_no,
            'mobile' => $mobile,
            'mobileMasked' => $mobile ? '*******'.substr($mobile, -4) : null,
            'gender' => $member->gender,
            'avatarUrl' => $account?->avatar_url,
            'status' => $member->status,
            'capabilities' => $capabilities,
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'code' => $role->code,
            ] : null,
            'isSiteOwner' => (int) $site->owner_staff_id === $member->id,
            'hasWechatBinding' => (bool) $account?->wechatIdentities?->isNotEmpty(),
            'inviteSign' => ! ($account?->wechatIdentities?->isNotEmpty() ?? false) && $member->status === 'active'
                ? $this->inviteTokens->issue($member, $site)['sign']
                : null,
            'hasFutureBookings' => $includePreflight ? $this->preflight->hasFutureBookings($member, $site) : null,
            'joinedOn' => $member->joined_on?->toDateString(),
            'leftOn' => $member->left_on?->toDateString(),
            'version' => $member->version,
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeCapabilities(mixed $capabilities): array
    {
        if (is_array($capabilities)) {
            return array_values($capabilities);
        }
        if (is_string($capabilities) && $capabilities !== '') {
            return array_values(json_decode($capabilities, true) ?: []);
        }

        return [];
    }
}
