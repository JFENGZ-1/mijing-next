<?php

namespace Database\Seeders;

use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Models\Course;
use App\Models\Room;
use Database\Seeders\Support\DemoSeedTarget;
use Illuminate\Database\Seeder;

class CourseCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            return;
        }

        $site = DemoSeedTarget::site();
        if (! $site) {
            $this->command?->warn('CourseCatalogSeeder skipped: site 1 missing.');

            return;
        }

        $room = Room::query()->firstOrCreate(
            ['tenant_id' => $site->tenant_id, 'site_id' => $site->id, 'name' => 'A教室'],
            [
                'capacity' => 20,
                'catalog_status' => CourseCatalogStatus::Active,
                'sort_order' => 10,
                'version' => 1,
            ],
        );

        Course::query()->firstOrCreate(
            ['tenant_id' => $site->tenant_id, 'site_id' => $site->id, 'name' => '瑜伽团课'],
            [
                'course_type' => CourseType::Group,
                'description' => '本地演示团课模板',
                'duration_minutes' => 60,
                'difficulty' => 2,
                'min_capacity' => 3,
                'max_capacity' => 12,
                'default_room_id' => $room->id,
                'tags' => ['瑜伽', '初级'],
                'catalog_status' => CourseCatalogStatus::Active,
                'sort_order' => 10,
                'version' => 1,
            ],
        );

        Course::query()->firstOrCreate(
            ['tenant_id' => $site->tenant_id, 'site_id' => $site->id, 'name' => '私教体验课'],
            [
                'course_type' => CourseType::Private,
                'description' => '本地演示私教模板',
                'duration_minutes' => 60,
                'catalog_status' => CourseCatalogStatus::Active,
                'sort_order' => 20,
                'version' => 1,
            ],
        );

        $this->command?->info('CourseCatalogSeeder: sample courses and room ready for site 1.');
    }
}
