<?php

namespace App\Services\Booking;

use App\Enums\ScheduleSessionStatus;
use App\Models\ScheduleSession;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BookingDayBoardQueryService
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function dayBounds(Site $site, string $date): array
    {
        $timezone = $site->timezone ?: (string) config('app.timezone');
        $dayStart = Carbon::parse($date, $timezone)->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();

        return [$dayStart, $dayEnd];
    }

    public function sessionsForDay(Site $site, string $date, ?ScheduleSessionStatus $status = null): Builder
    {
        [$dayStart, $dayEnd] = $this->dayBounds($site, $date);

        $query = ScheduleSession::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->where('starts_at', '>=', $dayStart)
            ->where('starts_at', '<', $dayEnd);

        if ($status) {
            $query->where('status', $status);
        }

        return $query;
    }

    /**
     * @return Collection<int, ScheduleSession>
     */
    public function memberCatalogSessions(Site $site, string $date): Collection
    {
        return $this->sessionsForDay($site, $date, ScheduleSessionStatus::Scheduled)
            ->with(['course', 'coach'])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, ScheduleSession>
     */
    public function staffDailyBoardSessions(Site $site, string $date): Collection
    {
        return $this->sessionsForDay($site, $date)
            ->with(['course', 'room', 'coach'])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();
    }
}
