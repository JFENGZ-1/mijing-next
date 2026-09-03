<?php

namespace App\Services\Booking;

use App\Enums\ScheduleSessionKind;
use App\Models\ScheduleSession;
use App\Models\Site;

class PublicBookingSharePresenter
{
    /**
     * Marketing-only session summary for share landings. No member or staff PII.
     *
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public static function toArray(Site $site, ScheduleSession $session, array $policy, string $expiresAt): array
    {
        $session->loadMissing(['course', 'coach', 'room']);
        $timezone = $site->timezone ?: (string) config('app.timezone');
        $date = $session->starts_at?->timezone($timezone)->toDateString();

        $showBookedCount = $session->session_kind === ScheduleSessionKind::Group
            ? (bool) $policy['group']['showBookedCount']
            : true;
        $waitlistEnabled = $session->session_kind === ScheduleSessionKind::Group
            && (bool) $policy['group']['waitlistEnabled'];

        return [
            'tenantId' => $session->tenant_id,
            'siteId' => $session->site_id,
            'siteName' => $site->name,
            'sessionId' => $session->id,
            'courseId' => $session->course_id,
            'courseName' => $session->course?->name,
            'courseType' => $session->course?->course_type->value,
            'sessionKind' => $session->session_kind->value,
            'sessionStatus' => $session->status->value,
            'startsAt' => $session->starts_at?->toIso8601String(),
            'endsAt' => $session->ends_at?->toIso8601String(),
            'date' => $date,
            'roomName' => $session->room?->name,
            'coachName' => $session->coach?->name,
            'durationMinutes' => $session->course?->duration_minutes,
            'description' => $session->course?->description,
            'capacity' => $session->capacity,
            'bookedCount' => $showBookedCount ? $session->booked_count : null,
            'waitlistEnabled' => $waitlistEnabled,
            'shareExpiresAt' => $expiresAt,
            'shareNotice' => 'Share links and legacy sign query parameters are not authentication. Booking actions require a member session.',
        ];
    }
}
