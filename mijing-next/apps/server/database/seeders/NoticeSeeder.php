<?php

namespace Database\Seeders;

use App\Models\SiteNotice;
use Database\Seeders\Support\DemoSeedTarget;
use Illuminate\Database\Seeder;

class NoticeSeeder extends Seeder
{
    public function run(): void
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            return;
        }

        $site = DemoSeedTarget::site();
        if (! $site) {
            $this->command?->warn('NoticeSeeder skipped: site 1 missing.');

            return;
        }

        $notices = [
            [
                'title' => '欢迎加入觅境瑜伽',
                'body' => '感谢您选择觅境瑜伽。请提前预约课程，按时到场签到。',
                'sort_order' => 1,
            ],
            [
                'title' => '夏季课程安排调整',
                'body' => '7 月起部分团课时间略有调整，请在约课页查看最新课表。',
                'sort_order' => 2,
            ],
        ];

        foreach ($notices as $notice) {
            SiteNotice::updateOrCreate(
                [
                    'tenant_id' => $site->tenant_id,
                    'site_id' => $site->id,
                    'title' => $notice['title'],
                ],
                [
                    'body' => $notice['body'],
                    'status' => 'published',
                    'published_at' => now()->subDays($notice['sort_order']),
                    'sort_order' => $notice['sort_order'],
                ],
            );
        }

        $this->command?->info('NoticeSeeder: site 1 notices seeded.');
    }
}
