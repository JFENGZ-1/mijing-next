<?php

namespace Database\Seeders;

use App\Enums\CourseType;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Course;
use App\Models\Room;
use App\Models\ScheduleSession;
use App\Models\Staff;
use Database\Seeders\Support\DemoSeedTarget;
use Illuminate\Database\Seeder;

class ScheduleSessionSeeder extends Seeder
{
    public function run(): void
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            return;
        }

        $site = DemoSeedTarget::site();
        if (! $site) {
            $this->command?->warn('ScheduleSessionSeeder skipped: site 1 missing.');

            return;
        }

        $groupCourse = Course::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->where('course_type', CourseType::Group)
            ->first();
        $privateCourse = Course::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->where('course_type', CourseType::Private)
            ->first();
        $room = Room::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('site_id', $site->id)
            ->first();
        $coach = Staff::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('status', 'active')
            ->first();

        if (! $groupCourse || ! $privateCourse || ! $coach) {
            $this->command?->warn('ScheduleSessionSeeder skipped: course or coach missing for site 1.');

            return;
        }

        $sessions = [
            [
                'course_id' => $groupCourse->id,
                'room_id' => $room?->id,
                'coach_staff_id' => $coach->id,
                'starts_at' => now()->addDay()->setTime(10, 0),
                'ends_at' => now()->addDay()->setTime(11, 0),
                'capacity' => 12,
                'session_kind' => ScheduleSessionKind::Group,
            ],
            [
                'course_id' => $groupCourse->id,
                'room_id' => $room?->id,
                'coach_staff_id' => $coach->id,
                'starts_at' => now()->addDays(3)->setTime(18, 30),
                'ends_at' => now()->addDays(3)->setTime(19, 30),
                'capacity' => 12,
                'session_kind' => ScheduleSessionKind::Group,
            ],
            [
                'course_id' => $privateCourse->id,
                'room_id' => null,
                'coach_staff_id' => $coach->id,
                'starts_at' => now()->addDays(5)->setTime(14, 0),
                'ends_at' => now()->addDays(5)->setTime(15, 0),
                'capacity' => 1,
                'session_kind' => ScheduleSessionKind::Private,
            ],
        ];

        foreach ($sessions as $attrs) {
            ScheduleSession::query()->firstOrCreate(
                [
                    'tenant_id' => $site->tenant_id,
                    'site_id' => $site->id,
                    'course_id' => $attrs['course_id'],
                    'starts_at' => $attrs['starts_at'],
                ],
                [
                    ...$attrs,
                    'booked_count' => 0,
                    'status' => ScheduleSessionStatus::Scheduled,
                    'version' => 1,
                ],
            );
        }

        $this->command?->info('ScheduleSessionSeeder: sample sessions ready for site 1.');
    }
}
