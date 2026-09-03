<?php

namespace App\Services\Compensation;

use App\Models\CompensationRole;
use App\Models\ConsumptionEvent;
use App\Models\ScheduleSession;
use App\Models\ScheduleSessionStaffAssignment;
use App\Models\Site;
use App\Models\Staff;
use App\Models\StaffCompensationRoleAssignment;
use App\Support\DomainActor;
use Illuminate\Support\Facades\DB;

class ScheduleSessionDeliveryAssignmentService
{
    /**
     * @param  list<array{staffId:int,compensationRoleId:int,allocationBps?:int,isPrimary?:bool}>  $assignments
     * @return list<ScheduleSessionStaffAssignment>
     */
    public function replace(
        DomainActor $actor,
        Site $site,
        ScheduleSession $session,
        array $assignments,
        string $commandKey,
        ?int $expectedVersion = null,
        ?string $reason = null,
    ): array {
        abort_unless($session->tenant_id === $site->tenant_id && $session->site_id === $site->id, 404);
        $normalizedCollection = collect($assignments)->map(fn ($assignment) => [
            'staffId' => (int) $assignment['staffId'],
            'compensationRoleId' => (int) $assignment['compensationRoleId'],
            'allocationBps' => (int) ($assignment['allocationBps'] ?? 10000),
            'isPrimary' => (bool) ($assignment['isPrimary'] ?? false),
        ])->sortBy(fn ($assignment) => implode(':', [
            $assignment['compensationRoleId'], $assignment['staffId'], $assignment['isPrimary'] ? 0 : 1,
        ]))->values();
        abort_if($normalizedCollection->isEmpty(), 422, 'SESSION_DELIVERY_ASSIGNMENTS_REQUIRED');
        abort_if(
            $normalizedCollection->unique(fn ($assignment) => $assignment['staffId'].':'.$assignment['compensationRoleId'])->count()
                !== $normalizedCollection->count(),
            422,
            'SESSION_DELIVERY_ASSIGNMENT_DUPLICATE',
        );
        abort_if($normalizedCollection->where('isPrimary', true)->count() > 1, 422, 'SESSION_PRIMARY_DELIVERY_INVALID');
        if ($normalizedCollection->isNotEmpty() && $normalizedCollection->where('isPrimary', true)->isEmpty()) {
            $normalizedCollection = $normalizedCollection->map(function ($assignment, $index) {
                $assignment['isPrimary'] = $index === 0;

                return $assignment;
            });
        }
        $normalized = $normalizedCollection->all();
        $payloadHash = hash('sha256', json_encode([
            'assignments' => $normalized, 'reason' => $reason,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $actor, $site, $session, $normalized, $payloadHash, $commandKey, $expectedVersion, $reason,
        ) {
            $locked = ScheduleSession::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('site_id', $site->id)
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();
            $receipt = DB::table('schedule_session_assignment_commands')
                ->where('tenant_id', $site->tenant_id)
                ->where('command_key', $commandKey)
                ->lockForUpdate()
                ->first();
            if ($receipt) {
                abort_unless(
                    (int) $receipt->schedule_session_id === $locked->id
                    && hash_equals($receipt->payload_hash, $payloadHash),
                    409,
                    'IDEMPOTENCY_KEY_REUSED',
                );

                return $this->forSession($locked);
            }
            if ($expectedVersion !== null) {
                abort_unless($locked->version === $expectedVersion, 409, 'SCHEDULE_SESSION_VERSION_CONFLICT');
            }
            abort_if(ConsumptionEvent::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('session_id', $locked->id)
                ->exists(), 409, 'SESSION_DELIVERY_ASSIGNMENTS_ALREADY_SETTLED');
            $roleIds = collect($normalized)->pluck('compensationRoleId')->unique();
            $roles = CompensationRole::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('site_id', $site->id)
                ->where('role_type', 'delivery')
                ->where('status', 'active')
                ->whereIn('id', $roleIds)
                ->get()
                ->keyBy('id');
            abort_unless($roles->count() === $roleIds->count(), 422, 'SESSION_DELIVERY_ROLE_INVALID');
            $staffIds = collect($normalized)->pluck('staffId')->unique();
            $staff = Staff::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('status', 'active')
                ->whereIn('id', $staffIds)
                ->whereHas('sites', fn ($query) => $query->whereKey($site->id))
                ->get();
            abort_unless($staff->count() === $staffIds->count(), 422, 'SESSION_DELIVERY_STAFF_INVALID');
            $businessDate = $locked->starts_at
                ->copy()
                ->timezone($site->timezone ?: config('app.timezone'))
                ->toDateString();
            foreach ($normalized as $assignment) {
                $ownsRole = StaffCompensationRoleAssignment::query()
                    ->where('tenant_id', $site->tenant_id)
                    ->where('site_id', $site->id)
                    ->where('staff_id', $assignment['staffId'])
                    ->where('compensation_role_id', $assignment['compensationRoleId'])
                    ->whereIn('status', ['active', 'archived'])
                    ->where(fn ($query) => $query->whereNull('active_from')->orWhere('active_from', '<=', $businessDate))
                    ->where(fn ($query) => $query->whereNull('active_until')->orWhere('active_until', '>=', $businessDate))
                    ->exists();
                abort_unless($ownsRole, 422, 'SESSION_DELIVERY_ROLE_NOT_ASSIGNED');
            }
            foreach (collect($normalized)->groupBy('compensationRoleId') as $roleAssignments) {
                abort_unless(
                    $roleAssignments->sum('allocationBps') === 10000,
                    422,
                    'SESSION_DELIVERY_ALLOCATION_INVALID',
                );
            }

            $nextVersion = $locked->version + 1;
            ScheduleSessionStaffAssignment::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('schedule_session_id', $locked->id)
                ->delete();
            foreach ($normalized as $assignment) {
                ScheduleSessionStaffAssignment::create([
                    'tenant_id' => $site->tenant_id,
                    'site_id' => $site->id,
                    'schedule_session_id' => $locked->id,
                    'staff_id' => $assignment['staffId'],
                    'compensation_role_id' => $assignment['compensationRoleId'],
                    'allocation_bps' => $assignment['allocationBps'],
                    'is_primary' => $assignment['isPrimary'],
                    'assignment_version' => $nextVersion,
                ]);
            }
            $primary = collect($normalized)->firstWhere('isPrimary', true) ?? ($normalized[0] ?? null);
            $locked->update([
                'delivery_role_id' => $primary['compensationRoleId'] ?? null,
                'coach_staff_id' => $primary['staffId'] ?? $locked->coach_staff_id,
                'version' => $nextVersion,
            ]);
            DB::table('schedule_session_assignment_commands')->insert([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'schedule_session_id' => $locked->id,
                'command_key' => $commandKey,
                'payload_hash' => $payloadHash,
                'result_version' => $nextVersion,
                'actor_type' => $actor->type,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            return $this->forSession($locked->fresh());
        });
    }

    /** @return list<ScheduleSessionStaffAssignment> */
    public function forSession(ScheduleSession $session): array
    {
        return ScheduleSessionStaffAssignment::query()
            ->where('tenant_id', $session->tenant_id)
            ->where('schedule_session_id', $session->id)
            ->with(['staff', 'role'])
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function present(ScheduleSessionStaffAssignment $assignment): array
    {
        $assignment->loadMissing(['staff', 'role']);

        return [
            'id' => $assignment->id,
            'staffId' => $assignment->staff_id,
            'staffName' => $assignment->staff?->name,
            'compensationRoleId' => $assignment->compensation_role_id,
            'roleName' => $assignment->role?->name,
            'roleType' => $assignment->role?->role_type,
            'allocationBps' => $assignment->allocation_bps,
            'isPrimary' => $assignment->is_primary,
            'assignmentVersion' => $assignment->assignment_version,
        ];
    }
}
