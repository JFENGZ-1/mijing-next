<?php

namespace Database\Seeders;

use App\Models\BookingPolicy;
use App\Models\Site;
use App\Services\Booking\BookingPolicyService;
use Illuminate\Database\Seeder;

class BookingPolicySeeder extends Seeder
{
    public function run(): void
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            return;
        }

        $site = Site::query()->find(1);
        if (! $site) {
            $this->command?->warn('BookingPolicySeeder skipped: site 1 missing.');

            return;
        }

        $defaults = BookingPolicyService::defaultPolicy();
        $defaults['group']['waitlistEnabled'] = true;
        $defaults['group']['showBookedCount'] = true;
        $defaults['group']['calendarDisplayDays'] = 14;
        $defaults['private']['slotIntervalMinutes'] = 30;
        $defaults['rules'] = [
            'legacyKeys' => [
                'signTime_team',
                'aheadAppointTime_team',
                'endAppointTime_team',
                'cancelAppointTime_team',
                'lineup_team',
                'showPeople_team',
                'cancelOpenCourse_team',
                'showCourseCalendar_team',
                'absent_team',
                'aheadAppointTime_private',
                'endAppointTime_private',
                'cancelAppointTime_private',
                'privateTimeInterval',
                'courseRestTime',
                'beyondTime_private',
                'teamCourse_private',
                'absent_private',
            ],
        ];

        BookingPolicy::updateOrCreate(
            [
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
            ],
            [
                'version' => 1,
                'policy' => [
                    'group' => $defaults['group'],
                    'private' => $defaults['private'],
                ],
                'rules' => $defaults['rules'],
            ],
        );
    }
}
