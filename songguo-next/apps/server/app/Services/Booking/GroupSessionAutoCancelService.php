<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\BookingPolicy;
use App\Models\ScheduleSession;
use Illuminate\Support\Str;

/**
 * 团课「未满足最低开课人数自动取消」（对标 cancelOpenCourse_team）。
 */
class GroupSessionAutoCancelService
{
    public function __construct(
        private readonly AppointmentWriteService $appointments,
        private readonly BookingPolicyService $policies,
    ) {}

    public function run(int $limit = 200): int
    {
        $processed = 0;
        $policies = BookingPolicy::query()->get();

        foreach ($policies as $stored) {
            $policy = $this->normalizePolicy($stored);
            if (! (bool) ($policy['group']['autoCancelUnderMinStudentsEnabled'] ?? false)) {
                continue;
            }

            $minutes = (int) ($policy['group']['autoCancelUnderMinStudentsMinutesBeforeStart'] ?? 180);
            $windowEnd = now()->addMinutes(max(0, $minutes));

            $sessions = ScheduleSession::query()
                ->where('tenant_id', $stored->tenant_id)
                ->where('site_id', $stored->site_id)
                ->where('session_kind', ScheduleSessionKind::Group)
                ->where('status', ScheduleSessionStatus::Scheduled)
                ->where('starts_at', '>', now())
                ->where('starts_at', '<=', $windowEnd)
                ->with('course:id,min_capacity')
                ->limit($limit)
                ->get();

            foreach ($sessions as $session) {
                if ($processed >= $limit) {
                    break 2;
                }

                $minRequired = max(1, (int) ($session->course?->min_capacity ?? 1));
                if ($session->booked_count >= $minRequired) {
                    continue;
                }

                $active = $session->appointments()
                    ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Waitlisted])
                    ->get();

                foreach ($active as $appointment) {
                    $this->appointments->cancelForSystem(
                        $stored->tenant_id,
                        $appointment,
                        (string) Str::uuid(),
                    );
                    $processed++;
                }

                $session->refresh();
                if ($session->status === ScheduleSessionStatus::Scheduled && $session->booked_count === 0) {
                    $session->update(['status' => ScheduleSessionStatus::Cancelled]);
                }
            }
        }

        return $processed;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePolicy(BookingPolicy $stored): array
    {
        return $this->policies->policyForTenantSite($stored->tenant_id, $stored->site_id);
    }
}
