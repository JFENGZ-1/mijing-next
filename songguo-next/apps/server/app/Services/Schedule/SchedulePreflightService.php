<?php

namespace App\Services\Schedule;

use App\Enums\ScheduleSessionStatus;
use App\Models\Course;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SchedulePreflightService
{
    public function copyPreflight(Staff $staff, Site $site, array $payload): array
    {
        $sources = $this->resolveCopySources($staff, $site, $payload);
        $dayOffset = $this->resolveCopyDayOffset($payload, $sources);
        $conflicts = [];

        foreach ($sources->values() as $session) {
            $targetStarts = $session->starts_at->copy()->addDays($dayOffset);
            $targetEnds = $session->ends_at->copy()->addDays($dayOffset);

            if ($this->hasRoomConflict($staff->tenant_id, $site->id, $session->room_id, $targetStarts, $targetEnds)) {
                $conflicts[] = [
                    'sourceSessionId' => $session->id,
                    'code' => 'SCHEDULE_SESSION_ROOM_CONFLICT',
                    'targetStartsAt' => $targetStarts->toIso8601String(),
                ];
            }
        }

        return [
            'sourceCount' => $sources->count(),
            'dayOffset' => $dayOffset,
            'canProceed' => $sources->isNotEmpty() && $conflicts === [],
            'conflicts' => $conflicts,
        ];
    }

    public function changeCoursePreflight(Staff $staff, Site $site, array $payload): array
    {
        $sessions = $this->resolveSessions($staff, $site, $payload['sessionIds']);
        $targetCourseId = (int) $payload['targetCourseId'];
        $courseExists = Course::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereKey($targetCourseId)
            ->exists();

        abort_unless($courseExists, 422, 'SCHEDULE_SESSION_COURSE_INVALID');

        $blocked = [];
        foreach ($sessions as $session) {
            if ($session->booked_count > 0) {
                $blocked[] = [
                    'sessionId' => $session->id,
                    'code' => 'SCHEDULE_CHANGE_COURSE_HAS_BOOKINGS',
                    'bookedCount' => $session->booked_count,
                ];
            }
        }

        return [
            'targetCourseId' => $targetCourseId,
            'sessionCount' => $sessions->count(),
            'canProceed' => $blocked === [],
            'blocked' => $blocked,
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
            ->get();
    }

    private function resolveCopyDayOffset(array $payload, Collection $sources): int
    {
        if (isset($payload['dayOffset'])) {
            return (int) $payload['dayOffset'];
        }

        abort_if($sources->isEmpty(), 422, 'SCHEDULE_BATCH_COPY_EMPTY');

        $targetFrom = Carbon::parse($payload['targetFrom'])->startOfDay();
        $sourceAnchor = $sources->min(fn (ScheduleSession $session) => $session->starts_at)->copy()->startOfDay();

        return (int) $sourceAnchor->diffInDays($targetFrom, false);
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

    private function hasRoomConflict(
        int $tenantId,
        int $siteId,
        ?int $roomId,
        Carbon $startsAt,
        Carbon $endsAt,
    ): bool {
        if ($roomId === null) {
            return false;
        }

        return ScheduleSession::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $siteId)
            ->where('room_id', $roomId)
            ->whereIn('status', [
                ScheduleSessionStatus::Scheduled->value,
                ScheduleSessionStatus::Suspended->value,
            ])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
    }
}
