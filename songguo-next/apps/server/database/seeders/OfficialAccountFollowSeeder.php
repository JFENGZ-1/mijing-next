<?php

namespace Database\Seeders;

use App\Models\Site;
use App\Models\SiteOfficialAccountFollow;
use Illuminate\Database\Seeder;

class OfficialAccountFollowSeeder extends Seeder
{
    public function run(): void
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            return;
        }

        $site = Site::query()->find(1);
        if (! $site) {
            $this->command?->warn('OfficialAccountFollowSeeder skipped: site 1 missing.');

            return;
        }

        SiteOfficialAccountFollow::updateOrCreate(
            ['tenant_id' => $site->tenant_id, 'site_id' => $site->id],
            [
                'image_url' => 'https://cdn.example.com/songguo/official-account-qr.png',
                'instructions_text' => '【提醒通知】即可收到课程取消、上课提醒、排队成功等通知',
                'status' => 'published',
            ],
        );

        $this->command?->info('OfficialAccountFollowSeeder: site 1 follow content seeded.');
    }
}
