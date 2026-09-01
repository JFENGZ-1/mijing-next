<?php

namespace App\Services\Compensation;

use App\Models\CompensationRole;
use App\Models\Site;
use App\Models\Staff;
use App\Models\StaffCompensationRoleAssignment;
use App\Support\DomainActor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompensationRoleService
{
    public function queryForSite(int $tenantId, int $siteId, array $filters = []): Builder
    {
        return CompensationRole::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $siteId)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['query'] ?? null, function (Builder $query, string $term) {
                $escaped = addcslashes(trim($term), '%_\\');
                $query->where(fn (Builder $nested) => $nested
                    ->where('name', 'like', "%{$escaped}%")
                    ->orWhere('code', 'like', "%{$escaped}%"));
            })
            ->orderBy('role_type')
            ->orderBy('name')
            ->orderBy('id');
    }

    public function create(DomainActor $actor, Site $site, array $payload): CompensationRole
    {
        $this->assertRoleType((string) $payload['roleType']);
        $code = trim((string) ($payload['code'] ?? Str::slug((string) $payload['name'])));
        if ($code === '') {
            $code = 'role-'.strtolower((string) Str::ulid());
        }
        $commandKey = $payload['commandKey'] ?? null;
        $reason = $payload['reason'] ?? null;
        $hash = $this->payloadHash([
            'action' => 'create', 'siteId' => $site->id, 'name' => $payload['name'],
            'code' => $payload['code'] ?? null, 'roleType' => $payload['roleType'], 'reason' => $reason,
        ]);

        return DB::transaction(function () use ($actor, $site, $payload, $code, $commandKey, $reason, $hash) {
            if ($commandKey !== null && ($receipt = $this->roleReceipt($site, $commandKey, $hash, 'create')) !== null) {
                return CompensationRole::query()
                    ->where('tenant_id', $site->tenant_id)->where('site_id', $site->id)
                    ->findOrFail($receipt->compensation_role_id);
            }
            Site::query()->where('tenant_id', $site->tenant_id)->whereKey($site->id)->lockForUpdate()->firstOrFail();
            if ($commandKey !== null && ($receipt = $this->roleReceipt($site, $commandKey, $hash, 'create')) !== null) {
                return CompensationRole::query()->findOrFail($receipt->compensation_role_id);
            }
            $role = CompensationRole::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'code' => $code,
                'name' => $payload['name'],
                'role_type' => $payload['roleType'],
                'status' => 'active',
                'version' => 1,
                'created_by_staff_id' => $actor->staffId(),
            ]);
            $this->recordRoleReceipt($actor, $site, $role, 'create', $commandKey, $hash, $reason);

            return $role;
        });
    }

    public function update(DomainActor $actor, Site $site, CompensationRole $role, array $payload): CompensationRole
    {
        abort_unless(
            $role->tenant_id === $site->tenant_id && $role->site_id === $site->id,
            404,
        );
        $roleType = (string) ($payload['roleType'] ?? $role->role_type);
        $this->assertRoleType($roleType);
        abort_unless($roleType === $role->role_type, 422, 'COMPENSATION_ROLE_TYPE_IMMUTABLE');
        $status = (string) ($payload['status'] ?? $role->status);
        abort_unless(in_array($status, ['active', 'archived'], true), 422, 'COMPENSATION_ROLE_STATUS_INVALID');
        $action = $status === 'archived' ? 'archive' : 'update';
        $commandKey = $payload['commandKey'] ?? null;
        $reason = $payload['reason'] ?? null;
        $hash = $this->payloadHash([
            'action' => $action, 'roleId' => $role->id, 'version' => (int) $payload['version'],
            'name' => $payload['name'] ?? null, 'code' => $payload['code'] ?? null,
            'roleType' => $roleType, 'status' => $status, 'reason' => $reason,
        ]);

        return DB::transaction(function () use ($actor, $site, $role, $payload, $roleType, $status, $action, $commandKey, $reason, $hash) {
            if ($commandKey !== null && $this->roleReceipt($site, $commandKey, $hash, $action) !== null) {
                return $role->fresh();
            }
            $locked = CompensationRole::query()
                ->where('tenant_id', $site->tenant_id)->where('site_id', $site->id)
                ->whereKey($role->id)->lockForUpdate()->firstOrFail();
            if ($commandKey !== null && $this->roleReceipt($site, $commandKey, $hash, $action) !== null) {
                return $locked;
            }
            abort_unless((int) $payload['version'] === $locked->version, 409, 'COMPENSATION_ROLE_VERSION_CONFLICT');
            abort_unless($roleType === $locked->role_type, 422, 'COMPENSATION_ROLE_TYPE_IMMUTABLE');
            $locked->update([
                'name' => $payload['name'] ?? $locked->name,
                'code' => $payload['code'] ?? $locked->code,
                'status' => $status,
                'archived_at' => $status === 'archived' ? now() : null,
                'version' => $locked->version + 1,
            ]);
            $this->recordRoleReceipt($actor, $site, $locked, $action, $commandKey, $hash, $reason);

            return $locked->fresh();
        });
    }

    /**
     * @param  list<array{compensationRoleId:int, activeFrom?:string|null, activeUntil?:string|null}>  $assignments
     * @return list<StaffCompensationRoleAssignment>
     */
    public function assignStaff(
        DomainActor $actor,
        Site $site,
        Staff $target,
        array $assignments,
        ?string $commandKey = null,
        ?string $reason = null,
    ): array {
        abort_unless($target->tenant_id === $site->tenant_id, 404);
        abort_unless($target->sites()->whereKey($site->id)->exists(), 404);

        $normalized = collect($assignments)->map(fn ($assignment) => [
            'compensationRoleId' => (int) $assignment['compensationRoleId'],
            'activeFrom' => $assignment['activeFrom'] ?? null,
            'activeUntil' => $assignment['activeUntil'] ?? null,
        ])->sortBy('compensationRoleId')->values()->all();
        abort_if(
            collect($normalized)->pluck('compensationRoleId')->unique()->count() !== count($normalized),
            422,
            'COMPENSATION_ROLE_ASSIGNMENT_DUPLICATE',
        );
        $hash = $this->payloadHash([
            'action' => 'replace', 'siteId' => $site->id, 'staffId' => $target->id,
            'assignments' => $normalized, 'reason' => $reason,
        ]);

        return DB::transaction(function () use ($actor, $site, $target, $normalized, $commandKey, $reason, $hash) {
            Staff::query()
                ->where('tenant_id', $site->tenant_id)
                ->whereKey($target->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($commandKey !== null && $this->assignmentReceipt($site, $target, $commandKey, $hash, 'replace') !== null) {
                return $this->currentAssignments($site, $target);
            }
            $roleIds = collect($normalized)->pluck('compensationRoleId')->unique()->values();
            $roles = CompensationRole::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('site_id', $site->id)
                ->where('status', 'active')
                ->whereIn('id', $roleIds)
                ->get()
                ->keyBy('id');
            abort_unless($roles->count() === $roleIds->count(), 422, 'COMPENSATION_ROLE_INVALID');

            $current = StaffCompensationRoleAssignment::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('site_id', $site->id)
                ->where('staff_id', $target->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();
            $today = now()->timezone($site->timezone ?: config('app.timezone'))->toDateString();
            $desired = collect($normalized)->keyBy(fn ($assignment) => (int) $assignment['compensationRoleId']);

            foreach ($current as $existing) {
                $assignment = $desired->get($existing->compensation_role_id);
                $from = $assignment !== null ? ($assignment['activeFrom'] ?? $today) : null;
                $until = $assignment['activeUntil'] ?? null;
                $unchanged = $assignment !== null
                    && $existing->active_from?->toDateString() === $from
                    && $existing->active_until?->toDateString() === $until;
                if ($unchanged) {
                    $desired->forget($existing->compensation_role_id);

                    continue;
                }

                $replacementStart = $assignment !== null ? ($assignment['activeFrom'] ?? $today) : null;
                $cutoff = $replacementStart !== null && $replacementStart > $today
                    ? Carbon::parse($replacementStart)->subDay()->toDateString()
                    : $today;
                $existingStart = $existing->active_from?->toDateString();
                if ($existingStart !== null && $existingStart > $cutoff) {
                    $existing->update([
                        'status' => 'cancelled',
                        // Keep the database date invariant valid. Cancelled rows are
                        // excluded by every effective-date lookup, so an empty interval
                        // is represented by status rather than active_until < active_from.
                        'active_until' => $existingStart,
                        'archived_at' => now(),
                    ]);

                    continue;
                }
                $endDate = $existing->active_until?->toDateString();
                if ($endDate === null || $endDate > $cutoff) {
                    $endDate = $cutoff;
                }
                $existing->update([
                    'status' => 'archived',
                    'active_until' => $endDate,
                    'archived_at' => now(),
                ]);
            }

            foreach ($desired->values() as $assignment) {
                // A null start date would make a newly-created assignment rewrite all historical
                // service dates. New assignments always begin on the site's current business day
                // unless the caller explicitly schedules another date.
                $from = $assignment['activeFrom'] ?? $today;
                $until = $assignment['activeUntil'] ?? null;
                abort_if($from !== null && $until !== null && $until < $from, 422, 'COMPENSATION_ROLE_DATE_INVALID');

                $version = ((int) StaffCompensationRoleAssignment::query()
                    ->where('tenant_id', $site->tenant_id)
                    ->where('site_id', $site->id)
                    ->where('staff_id', $target->id)
                    ->where('compensation_role_id', (int) $assignment['compensationRoleId'])
                    ->max('version')) + 1;
                StaffCompensationRoleAssignment::query()->create([
                    'tenant_id' => $site->tenant_id,
                    'site_id' => $site->id,
                    'staff_id' => $target->id,
                    'compensation_role_id' => (int) $assignment['compensationRoleId'],
                    'active_from' => $from,
                    'active_until' => $until,
                    'status' => 'active',
                    'version' => $version,
                    'assigned_by_staff_id' => $actor->staffId(),
                ]);
            }

            $saved = $this->currentAssignments($site, $target);
            $this->recordAssignmentReceipt(
                $actor, $site, $target, $saved[0] ?? null, 'replace', $commandKey, $hash, $reason,
            );

            return $saved;
        });
    }

    public function endStaffAssignment(
        DomainActor $actor,
        Site $site,
        StaffCompensationRoleAssignment $assignment,
        int $expectedVersion,
        string $effectiveUntil,
        string $reason,
        string $commandKey,
    ): StaffCompensationRoleAssignment {
        abort_unless($assignment->tenant_id === $site->tenant_id && $assignment->site_id === $site->id, 404);
        $hash = $this->payloadHash([
            'action' => 'end', 'assignmentId' => $assignment->id, 'version' => $expectedVersion,
            'effectiveUntil' => $effectiveUntil, 'reason' => $reason,
        ]);

        return DB::transaction(function () use ($actor, $site, $assignment, $expectedVersion, $effectiveUntil, $reason, $commandKey, $hash) {
            $target = Staff::query()->where('tenant_id', $site->tenant_id)
                ->whereKey($assignment->staff_id)->lockForUpdate()->firstOrFail();
            if ($this->assignmentReceipt($site, $target, $commandKey, $hash, 'end') !== null) {
                return $assignment->fresh();
            }
            $locked = StaffCompensationRoleAssignment::query()
                ->where('tenant_id', $site->tenant_id)->where('site_id', $site->id)
                ->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->version === $expectedVersion, 409, 'COMPENSATION_ASSIGNMENT_VERSION_CONFLICT');
            $start = $locked->active_from?->toDateString();
            abort_if($start !== null && $effectiveUntil < $start, 422, 'COMPENSATION_ROLE_DATE_INVALID');
            $today = now()->timezone($site->timezone ?: config('app.timezone'))->toDateString();
            $locked->update([
                'active_until' => $effectiveUntil,
                'status' => $effectiveUntil < $today ? 'archived' : 'active',
                'archived_at' => $effectiveUntil < $today ? now() : null,
                'version' => $locked->version + 1,
            ]);
            $this->recordAssignmentReceipt($actor, $site, $target, $locked, 'end', $commandKey, $hash, $reason);

            return $locked->fresh();
        });
    }

    public function present(CompensationRole $role): array
    {
        return [
            'id' => $role->id,
            'siteId' => $role->site_id,
            'code' => $role->code,
            'name' => $role->name,
            'roleType' => $role->role_type,
            'status' => $role->status,
            'version' => $role->version,
            'archivedAt' => $role->archived_at?->toIso8601String(),
        ];
    }

    private function assertRoleType(string $roleType): void
    {
        abort_unless(in_array($roleType, ['delivery', 'share'], true), 422, 'COMPENSATION_ROLE_TYPE_INVALID');
    }

    private function currentAssignments(Site $site, Staff $target): array
    {
        $today = now()->timezone($site->timezone ?: config('app.timezone'))->toDateString();

        return StaffCompensationRoleAssignment::query()
            ->where('tenant_id', $site->tenant_id)->where('site_id', $site->id)
            ->where('staff_id', $target->id)
            ->whereIn('status', ['active', 'archived'])
            ->where(fn ($query) => $query->whereNull('active_until')->orWhere('active_until', '>=', $today))
            ->with('role')
            ->orderBy('compensation_role_id')
            ->orderBy('active_from')
            ->orderBy('id')
            ->get()
            ->all();
    }

    private function roleReceipt(Site $site, string $commandKey, string $hash, string $action): ?object
    {
        $receipt = DB::table('compensation_role_commands')
            ->where('tenant_id', $site->tenant_id)->where('command_key', $commandKey)->first();
        if ($receipt !== null) {
            abort_unless((int) $receipt->site_id === $site->id
                && $receipt->action === $action && hash_equals($receipt->payload_hash, $hash), 409, 'IDEMPOTENCY_KEY_REUSED');
        }

        return $receipt;
    }

    private function assignmentReceipt(Site $site, Staff $target, string $commandKey, string $hash, string $action): ?object
    {
        $receipt = DB::table('staff_compensation_role_assignment_commands')
            ->where('tenant_id', $site->tenant_id)->where('command_key', $commandKey)->first();
        if ($receipt !== null) {
            abort_unless((int) $receipt->site_id === $site->id && (int) $receipt->staff_id === $target->id
                && $receipt->action === $action && hash_equals($receipt->payload_hash, $hash), 409, 'IDEMPOTENCY_KEY_REUSED');
        }

        return $receipt;
    }

    private function recordRoleReceipt(
        DomainActor $actor, Site $site, CompensationRole $role, string $action,
        ?string $commandKey, string $hash, ?string $reason,
    ): void {
        if ($commandKey === null) {
            return;
        }
        DB::table('compensation_role_commands')->insert([
            'tenant_id' => $site->tenant_id, 'site_id' => $site->id, 'compensation_role_id' => $role->id,
            'action' => $action, 'command_key' => $commandKey, 'payload_hash' => $hash,
            'actor_type' => $actor->type, 'actor_id' => $actor->id, 'reason' => $reason,
            'result_version' => $role->version, 'created_at' => now(),
        ]);
    }

    private function recordAssignmentReceipt(
        DomainActor $actor, Site $site, Staff $target, ?StaffCompensationRoleAssignment $assignment,
        string $action, ?string $commandKey, string $hash, ?string $reason,
    ): void {
        if ($commandKey === null) {
            return;
        }
        DB::table('staff_compensation_role_assignment_commands')->insert([
            'tenant_id' => $site->tenant_id, 'site_id' => $site->id, 'staff_id' => $target->id,
            'staff_compensation_role_assignment_id' => $assignment?->id, 'action' => $action,
            'command_key' => $commandKey, 'payload_hash' => $hash, 'actor_type' => $actor->type,
            'actor_id' => $actor->id, 'reason' => $reason, 'created_at' => now(),
        ]);
    }

    private function payloadHash(array $payload): string
    {
        $normalize = function ($value) use (&$normalize) {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($normalize, $value);
        };

        return hash('sha256', json_encode($normalize($payload), JSON_THROW_ON_ERROR));
    }
}
