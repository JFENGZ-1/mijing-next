<?php

namespace App\Services\Schedule;

use App\Enums\ScheduleSessionStatus;
use App\Models\Appointment;
use App\Models\ScheduleBatchCommand;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleBatchWriteService
{
    public function batchCopy(Staff $staff, Site $site, array $payload): array
    {
        return $this->runIdempotent($staff, $site, $payload['commandKey'], 'copy', function () use ($staff, $site, $payload) {
            $sources = $this->resolveCopySources($staff, $site, $payload);
            abort_if($sources->isEmpty(), 422, 'SCHEDULE_BATCH_COPY_EMPTY');

            $dayOffset = $this->resolveCopyDayOffset($payload, $sources);
            $targets = $sources->map(function (ScheduleSession $session) use ($dayOffset) {
                return [
                    'sourceSessionId' => $session->id,
                    'startsAt' => $session->starts_at->copy()->addDays($dayOffset)->toIso8601String(),
                    'endsAt' => $session->ends_at->copy()->addDays($dayOffset)->toIso8601String(),
                ];
            });

            $this->assertCopyTargetsAvailable($staff->tenant_id, $site->id, $sources, $targets);

            $created = DB::transaction(function () use ($staff, $site, $sources, $targets) {
                $createdSessions = [];

                foreach ($sources->values() as $index => $session) {
                    $target = $targets[$index];
                    $this->assertRoomConflict(
                        $staff->tenant_id,
                        $site->id,
                        $session->room_id,
                        $target['startsAt'],
                        $target['endsAt'],
                    );

                    $createdSessions[] = ScheduleSession::create([
                        'tenant_id' => $staff->tenant_id,
                        'site_id' => $site->id,
                        'course_id' => $session->course_id,
                        'room_id' => $session->room_id,
                        'coach_staff_id' => $session->coach_staff_id,
                        'starts_at' => $target['startsAt'],
                        'ends_at' => $target['endsAt'],
                        'capacity' => $session->capacity,
                        'booked_count' => 0,
                        'status' => ScheduleSessionStatus::Scheduled,
                        'session_kind' => $session->session_kind,
                        'version' => 1,
                        'created_by_staff_id' => $staff->id,
                    ]);
                }

                return $createdSessions;
            });

            return [
                'commandKey' => $payload['commandKey'],
                'createdSessionIds' => array_map(fn (ScheduleSession $session) => $session->id, $created),
            ];
        });
    }

    public function batchSuspend(Staff $staff, Site $site, array $payload): array
    {
        return $this->runIdempotent($staff, $site, $payload['commandKey'], 'suspend', function () use ($staff, $site, $payload) {
            $sessions = $this->resolveSessions($staff, $site, $payload['sessionIds']);
            $succeeded = [];

            DB::transaction(function () use ($sessions, $payload, &$succeeded) {
                foreach ($sessions as $session) {
                    if ($session->status === ScheduleSessionStatus::Suspended) {
                        $succeeded[] = $session->id;

                        continue;
                    }

                    abort_if(
                        in_array($session->status, [ScheduleSessionStatus::Cancelled, ScheduleSessionStatus::Completed], true),
                        409,
                        'SCHEDULE_SESSION_STATUS_CONFLICT',
                    );

                    $session->update(['status' => ScheduleSessionStatus::Suspended]);
                    $succeeded[] = $session->id;
                }
            });

            return [
                'commandKey' => $payload['commandKey'],
                'reason' => $payload['reason'] ?? null,
                'succeededSessionIds' => $succeeded,
                'failed' => [],
            ];
        });
    }

    public function batchCancel(Staff $staff, Site $site, array $payload): array
    {
        return $this->runIdempotent($staff, $site, $payload['commandKey'], 'cancel', function () use ($staff, $site, $payload) {
            $sessions = $this->resolveSessions($staff, $site, $payload['sessionIds']);
            $succeeded = [];
            $failed = [];

            DB::transaction(function () use ($sessions, &$succeeded, &$failed) {
                foreach ($sessions as $session) {
                    if ($this->sessionHasAppointmentHistory($session)) {
                        $failed[] = [
                            'sessionId' => $session->id,
                            'code' => 'SCHEDULE_BATCH_CANCEL_HAS_APPOINTMENTS',
                        ];

                        continue;
                    }

                    if ($session->status === ScheduleSessionStatus::Cancelled) {
                        $succeeded[] = $session->id;

                        continue;
                    }

                    abort_if(
                        $session->status === ScheduleSessionStatus::Completed,
                        409,
                        'SCHEDULE_SESSION_STATUS_CONFLICT',
                    );

                    $session->update(['status' => ScheduleSessionStatus::Cancelled]);
                    $succeeded[] = $session->id;
                }
            });

            return [
                'commandKey' => $payload['commandKey'],
                'reason' => $payload['reason'] ?? null,
                'succeededSessionIds' => $succeeded,
                'failed' => $failed,
            ];
        });
    }

    public function batchUnsuspend(Staff $staff, Site $site, array $payload): array
    {
        return $this->runIdempotent($staff, $site, $payload['commandKey'], 'unsuspend', function () use ($staff, $site, $payload) {
            $sessions = $this->resolveSessions($staff, $site, $payload['sessionIds']);
            $succeeded = [];

            DB::transaction(function () use ($sessions, &$succeeded) {
                foreach ($sessions as $session) {
                    if ($session->status === ScheduleSessionStatus::Scheduled) {
                        $succeeded[] = $session->id;

                        continue;
                    }

                    abort_unless($session->status === ScheduleSessionStatus::Suspended, 409, 'SCHEDULE_SESSION_STATUS_CONFLICT');

                    $session->update(['status' => ScheduleSessionStatus::Scheduled]);
                    $succeeded[] = $session->id;
                }
            });

            return [
                'commandKey' => $payload['commandKey'],
                'reason' => $payload['reason'] ?? null,
                'succeededSessionIds' => $succeeded,
                'failed' => [],
            ];
        });
    }

    public function batchChangeCourse(Staff $staff, Site $site, array $payload): array
    {
        return $this->runIdempotent($staff, $site, $payload['commandKey'], 'change-course', function () use ($staff, $site, $payload) {
            $sessions = $this->resolveSessions($staff, $site, $payload['sessionIds']);
            $targetCourseId = (int) $payload['targetCourseId'];
            $succeeded = [];
            $failed = [];

            DB::transaction(function () use ($staff, $site, $sessions, $targetCourseId, &$succeeded, &$failed) {
                foreach ($sessions as $session) {
                    if ($session->booked_count > 0) {
                        $failed[] = [
                            'sessionId' => $session->id,
                            'code' => 'SCHEDULE_BATCH_CHANGE_COURSE_HAS_BOOKINGS',
                        ];

                        continue;
                    }

                    if (in_array($session->status, [ScheduleSessionStatus::Cancelled, ScheduleSessionStatus::Completed], true)) {
                        $failed[] = [
                            'sessionId' => $session->id,
                            'code' => 'SCHEDULE_SESSION_STATUS_CONFLICT',
                        ];

                        continue;
                    }

                    $session->update(['course_id' => $targetCourseId]);
                    $succeeded[] = $session->id;
                }
            });

            return [
                'commandKey' => $payload['commandKey'],
                'targetCourseId' => $targetCourseId,
                'succeededSessionIds' => $succeeded,
                'failed' => $failed,
            ];
        });
    }

    private function runIdempotent(
        Staff $staff,
        Site $site,
        string $commandKey,
        string $operation,
        callable $handler,
    ): array {
        $existing = ScheduleBatchCommand::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('command_key', $commandKey)
            ->first();

        if ($existing !== null) {
            abort_if($existing->operation !== $operation, 409, 'SCHEDULE_BATCH_COMMAND_CONFLICT');

            return [
                ...$existing->result,
                'replay' => true,
            ];
        }

        $result = $handler();

        ScheduleBatchCommand::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $site->id,
            'command_key' => $commandKey,
            'operation' => $operation,
            'result' => $result,
            'created_by_staff_id' => $staff->id,
        ]);

        return [
            ...$result,
            'replay' => false,
        ];
    }

    private function resolveCopySources(Staff $staff, Site $site, array $payload): Collection
    {
        if (! empty($payload['sourceSessionIds'])) {
            return $this->resolveSessions($staff, $site, $payload['sourceSessionIds'])
                ->filter(fn (ScheduleSession $session) => in_array($session->status, [
                    ScheduleSessionStatus::Scheduled,
                    ScheduleSessionStatus::Suspended,
                ], true))
                ->sortBy('starts_at')
                ->values();
        }

        abort_unless(
            isset($payload['sourceFrom'], $payload['sourceTo'], $payload['targetFrom']),
            422,
            'SCHEDULE_BATCH_COPY_INPUT_INVALID',
        );

        return ScheduleSession::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('starts_at', '>=', $payload['sourceFrom'])
            ->where('starts_at', '<', $payload['sourceTo'])
            ->whereIn('status', [
                ScheduleSessionStatus::Scheduled->value,
                ScheduleSessionStatus::Suspended->value,
            ])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();
    }

    private function resolveCopyDayOffset(array $payload, Collection $sources): int
    {
        if (isset($payload['dayOffset'])) {
            return (int) $payload['dayOffset'];
        }

        abort_if($sources->isEmpty(), 422, 'SCHEDULE_BATCH_COPY_EMPTY');

        $targetFrom = Carbon::parse($payload['targetFrom'])->startOfDay();

        if (isset($payload['sourceFrom'])) {
            $sourceFrom = Carbon::parse($payload['sourceFrom'])->startOfDay();

            return (int) $sourceFrom->diffInDays($targetFrom, false);
        }

        $sourceAnchor = $sources->min(fn (ScheduleSession $session) => $session->starts_at)->copy()->startOfDay();

        return (int) $sourceAnchor->diffInDays($targetFrom, false);
    }

    private function assertCopyTargetsAvailable(
        int $tenantId,
        int $siteId,
        Collection $sources,
        Collection $targets,
    ): void {
        foreach ($sources->values() as $index => $session) {
            $target = $targets[$index];
            $this->assertRoomConflict(
                $tenantId,
                $siteId,
                $session->room_id,
                $target['startsAt'],
                $target['endsAt'],
            );
        }
    }

    private function resolveSessions(Staff $staff, Site $site, array $sessionIds): Collection
    {
        $uniqueIds = array_values(array_unique($sessionIds));
        $sessions = ScheduleSession::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereIn('id', $uniqueIds)
            ->get()
            ->keyBy('id');

        abort_if($sessions->count() !== count($uniqueIds), 404, 'NOT_FOUND');

        return collect($uniqueIds)->map(fn (int $id) => $sessions[$id]);
    }

    private function sessionHasAppointmentHistory(ScheduleSession $session): bool
    {
        return Appointment::query()
            ->where('tenant_id', $session->tenant_id)
            ->where('session_id', $session->id)
            ->exists();
    }

    private function assertRoomConflict(
        int $tenantId,
        int $siteId,
        ?int $roomId,
        string $startsAt,
        string $endsAt,
        ?int $excludeSessionId = null,
    ): void {
        if ($roomId === null) {
            return;
        }

        $query = ScheduleSession::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $siteId)
            ->where('room_id', $roomId)
            ->whereIn('status', [
                ScheduleSessionStatus::Scheduled->value,
                ScheduleSessionStatus::Suspended->value,
            ])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($excludeSessionId !== null) {
            $query->whereKeyNot($excludeSessionId);
        }

        abort_if($query->exists(), 409, 'SCHEDULE_SESSION_ROOM_CONFLICT');
    }
}
