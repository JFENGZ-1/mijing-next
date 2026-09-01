<?php

namespace App\Services\Compensation;

use App\Models\CompensationRole;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardShareAssignment;
use App\Models\Site;
use App\Models\Staff;
use App\Models\StaffCompensationRoleAssignment;
use App\Support\DomainActor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MemberCardShareAssignmentService
{
    public function replace(
        MemberCard $card,
        Site $site,
        array $assignments,
        ?DomainActor $actor = null,
        ?string $commandKey = null,
        ?string $reason = null,
        ?int $expectedVersion = null,
    ): array {
        abort_unless($card->tenant_id === $site->tenant_id && $card->site_id === $site->id, 404);
        $businessToday = now()->timezone($site->timezone ?: config('app.timezone'))->toDateString();

        return DB::transaction(function () use ($card, $site, $assignments, $actor, $commandKey, $reason, $expectedVersion, $businessToday) {
            $normalized = collect($assignments)->map(fn ($assignment) => [
                'staffId' => (int) $assignment['staffId'],
                'compensationRoleId' => (int) $assignment['compensationRoleId'],
                'allocationBps' => (int) ($assignment['allocationBps'] ?? 10000),
                'effectiveFrom' => $assignment['effectiveFrom'] ?? null,
                'effectiveUntil' => $assignment['effectiveUntil'] ?? null,
            ])->sortBy(fn ($assignment) => implode(':', [
                $assignment['compensationRoleId'], $assignment['staffId'],
                $assignment['effectiveFrom'], $assignment['effectiveUntil'] ?? '',
            ]))->values()->all();
            $payloadHash = hash('sha256', json_encode([
                'assignments' => $normalized, 'reason' => $reason, 'expectedVersion' => $expectedVersion,
            ], JSON_THROW_ON_ERROR));

            if ($commandKey !== null) {
                $existingCommand = DB::table('member_card_share_assignment_commands')
                    ->where('tenant_id', $card->tenant_id)
                    ->where('command_key', $commandKey)
                    ->first();
                if ($existingCommand) {
                    abort_unless(
                        (int) $existingCommand->member_card_id === $card->id
                        && hash_equals($existingCommand->payload_hash, $payloadHash),
                        409,
                        'IDEMPOTENCY_KEY_REUSED',
                    );

                    return $this->activeForCard($card, $businessToday)->all();
                }
            }

            $lockedCard = MemberCard::query()
                ->where('tenant_id', $card->tenant_id)
                ->whereKey($card->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($commandKey !== null) {
                $existingCommand = DB::table('member_card_share_assignment_commands')
                    ->where('tenant_id', $card->tenant_id)
                    ->where('command_key', $commandKey)
                    ->lockForUpdate()
                    ->first();
                if ($existingCommand) {
                    abort_unless(
                        (int) $existingCommand->member_card_id === $card->id
                        && hash_equals($existingCommand->payload_hash, $payloadHash),
                        409,
                        'IDEMPOTENCY_KEY_REUSED',
                    );

                    return $this->activeForCard($card, $businessToday)->all();
                }
            }
            if ($expectedVersion !== null) {
                abort_unless(
                    (int) $lockedCard->share_assignment_version === $expectedVersion,
                    409,
                    'SHARE_ASSIGNMENT_VERSION_CONFLICT',
                );
            }

            $assignments = collect($normalized)->map(function ($assignment) use ($businessToday) {
                $assignment['effectiveFrom'] ??= $businessToday;

                return $assignment;
            })->all();
            abort_if(
                collect($assignments)->unique(fn ($assignment) => $assignment['staffId'].':'.$assignment['compensationRoleId'])->count()
                    !== count($assignments),
                422,
                'SHARE_ASSIGNMENT_DUPLICATE',
            );

            $roleIds = collect($assignments)->pluck('compensationRoleId')->map(fn ($id) => (int) $id)->unique();
            $staffIds = collect($assignments)->pluck('staffId')->map(fn ($id) => (int) $id)->unique();
            $roles = CompensationRole::query()
                ->where('tenant_id', $card->tenant_id)
                ->where('site_id', $site->id)
                ->where('role_type', 'share')
                ->where('status', 'active')
                ->whereIn('id', $roleIds)
                ->get()
                ->keyBy('id');
            abort_unless($roles->count() === $roleIds->count(), 422, 'SHARE_ASSIGNMENT_ROLE_INVALID');
            $staff = Staff::query()
                ->where('tenant_id', $card->tenant_id)
                ->whereIn('id', $staffIds)
                ->where('status', 'active')
                ->whereHas('sites', fn ($query) => $query->whereKey($site->id))
                ->get()
                ->keyBy('id');
            abort_unless($staff->count() === $staffIds->count(), 422, 'SHARE_ASSIGNMENT_STAFF_INVALID');

            foreach (collect($assignments)->groupBy('compensationRoleId') as $roleAssignments) {
                $sum = $roleAssignments->sum(fn ($assignment) => (int) ($assignment['allocationBps'] ?? 10000));
                abort_unless($sum === 10000, 422, 'SHARE_ASSIGNMENT_ALLOCATION_INVALID');
                abort_unless($roleAssignments->pluck('effectiveFrom')->unique()->count() === 1, 422, 'SHARE_ASSIGNMENT_DATE_INVALID');
                abort_unless($roleAssignments->pluck('effectiveUntil')->unique()->count() === 1, 422, 'SHARE_ASSIGNMENT_DATE_INVALID');
            }
            foreach ($assignments as $assignment) {
                abort_if(
                    $assignment['effectiveUntil'] !== null
                    && $assignment['effectiveUntil'] < $assignment['effectiveFrom'],
                    422,
                    'SHARE_ASSIGNMENT_DATE_INVALID',
                );
                $targetStaff = $staff->get($assignment['staffId']);
                $ownsRole = $targetStaff !== null
                    && ($targetStaff->joined_on === null || $targetStaff->joined_on->toDateString() <= $assignment['effectiveFrom'])
                    && ($targetStaff->left_on === null
                        || ($assignment['effectiveUntil'] !== null
                            && $targetStaff->left_on->toDateString() >= $assignment['effectiveUntil']))
                    && $this->roleAssignmentCoversInterval(
                        (int) $card->tenant_id,
                        (int) $site->id,
                        (int) $assignment['staffId'],
                        (int) $assignment['compensationRoleId'],
                        (string) $assignment['effectiveFrom'],
                        $assignment['effectiveUntil'],
                    );
                abort_unless($ownsRole, 422, 'SHARE_ASSIGNMENT_ROLE_NOT_ASSIGNED');
            }

            $current = MemberCardShareAssignment::query()
                ->where('tenant_id', $card->tenant_id)
                ->where('member_card_id', $card->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();
            $nextStartByRole = collect($assignments)
                ->groupBy('compensationRoleId')
                ->map(fn ($items) => $items->min('effectiveFrom'));
            foreach ($current as $existing) {
                $nextStart = $nextStartByRole->get($existing->compensation_role_id);
                $cutoff = $nextStart !== null && $nextStart > $businessToday
                    ? Carbon::parse($nextStart)->subDay()->toDateString()
                    : $businessToday;
                $existing->update([
                    'status' => 'archived',
                    'archived_at' => now(),
                    'effective_until' => $cutoff,
                ]);
            }

            foreach (array_values($assignments) as $index => $assignment) {
                $version = ((int) MemberCardShareAssignment::query()
                    ->where('tenant_id', $card->tenant_id)
                    ->where('member_card_id', $card->id)
                    ->where('staff_id', $assignment['staffId'])
                    ->where('compensation_role_id', $assignment['compensationRoleId'])
                    ->max('version')) + 1;
                MemberCardShareAssignment::create([
                    'tenant_id' => $card->tenant_id,
                    'site_id' => $site->id,
                    'member_card_id' => $card->id,
                    'staff_id' => (int) $assignment['staffId'],
                    'compensation_role_id' => (int) $assignment['compensationRoleId'],
                    'allocation_bps' => (int) ($assignment['allocationBps'] ?? 10000),
                    'status' => 'active',
                    'version' => $version,
                    'effective_from' => $assignment['effectiveFrom'],
                    'effective_until' => $assignment['effectiveUntil'] ?? null,
                    'command_key' => $commandKey !== null ? $commandKey.':'.$index : null,
                    'assigned_by_staff_id' => $actor?->staffId(),
                ]);
            }

            $lockedCard->update([
                'share_assignment_version' => (int) $lockedCard->share_assignment_version + 1,
            ]);

            if ($commandKey !== null) {
                DB::table('member_card_share_assignment_commands')->insert([
                    'tenant_id' => $card->tenant_id,
                    'site_id' => $site->id,
                    'member_card_id' => $card->id,
                    'command_key' => $commandKey,
                    'payload_hash' => $payloadHash,
                    'actor_type' => $actor?->type,
                    'actor_id' => $actor?->id,
                    'reason' => $reason,
                    'result_version' => $lockedCard->share_assignment_version,
                    'created_at' => now(),
                ]);
            }

            return $this->activeForCard($card, $businessToday)->all();
        });
    }

    public function assignOwnerDefaults(MemberCard $card, Member $member, Site $site): array
    {
        if ($member->owner_staff_id === null) {
            return [];
        }

        $businessToday = now()->timezone($site->timezone ?: config('app.timezone'))->toDateString();
        $roles = StaffCompensationRoleAssignment::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('site_id', $site->id)
            ->where('staff_id', $member->owner_staff_id)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('active_from')->orWhere('active_from', '<=', $businessToday))
            ->where(fn ($query) => $query->whereNull('active_until')->orWhere('active_until', '>=', $businessToday))
            ->whereHas('role', fn ($query) => $query->where('role_type', 'share')->where('status', 'active'))
            ->get();
        if ($roles->isEmpty()) {
            return [];
        }

        return $this->replace($card, $site, $roles->map(fn ($assignment) => [
            'staffId' => $member->owner_staff_id,
            'compensationRoleId' => $assignment->compensation_role_id,
            'allocationBps' => 10000,
        ])->all(), commandKey: 'member-card:'.$card->id.':owner-share');
    }

    public function activeForCard(MemberCard $card, string $businessDate)
    {
        $matches = MemberCardShareAssignment::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('member_card_id', $card->id)
            ->whereIn('status', ['active', 'archived'])
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $businessDate))
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $businessDate))
            ->with(['role', 'staff'])
            ->orderBy('compensation_role_id')
            ->orderBy('staff_id')
            ->get();

        // Select independently per B role. A future replacement for one role must
        // never hide the still-effective archived set of another role.
        return $matches->groupBy('compensation_role_id')->flatMap(function ($roleMatches) {
            $active = $roleMatches->where('status', 'active');
            if ($active->isNotEmpty()) {
                return $active->values();
            }

            $latestCreatedAt = $roleMatches->max(fn ($assignment) => $assignment->created_at?->getTimestamp() ?? 0);

            return $roleMatches->filter(
                fn ($assignment) => ($assignment->created_at?->getTimestamp() ?? 0) === $latestCreatedAt,
            )->values();
        })->values();
    }

    private function roleAssignmentCoversInterval(
        int $tenantId,
        int $siteId,
        int $staffId,
        int $roleId,
        string $effectiveFrom,
        ?string $effectiveUntil,
    ): bool {
        $targetEnd = $effectiveUntil !== null ? Carbon::parse($effectiveUntil)->startOfDay() : null;
        $cursor = Carbon::parse($effectiveFrom)->startOfDay();
        $intervals = StaffCompensationRoleAssignment::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $siteId)
            ->where('staff_id', $staffId)
            ->where('compensation_role_id', $roleId)
            ->whereIn('status', ['active', 'archived'])
            ->where(fn ($query) => $query->whereNull('active_until')->orWhere('active_until', '>=', $effectiveFrom))
            ->when($effectiveUntil !== null, fn ($query) => $query
                ->where(fn ($dates) => $dates->whereNull('active_from')->orWhere('active_from', '<=', $effectiveUntil)))
            ->orderByRaw('active_from IS NOT NULL')
            ->orderBy('active_from')
            ->orderBy('id')
            ->get();

        foreach ($intervals as $interval) {
            $starts = $interval->active_from?->copy()->startOfDay();
            if ($starts !== null && $starts->greaterThan($cursor)) {
                return false;
            }
            $ends = $interval->active_until?->copy()->startOfDay();
            if ($ends === null) {
                return true;
            }
            if ($ends->lessThan($cursor)) {
                continue;
            }
            $cursor = $ends->copy()->addDay();
            if ($targetEnd !== null && $cursor->greaterThan($targetEnd)) {
                return true;
            }
        }

        return false;
    }
}
