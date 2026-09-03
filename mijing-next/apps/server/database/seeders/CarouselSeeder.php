<?php

namespace Database\Seeders;

use App\Models\SiteCarouselItem;
use Database\Seeders\Support\DemoSeedTarget;
use Illuminate\Database\Seeder;

class CarouselSeeder extends Seeder
{
    public function run(): void
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            return;
        }

        $site = DemoSeedTarget::site();
        if (! $site) {
            $this->command?->warn('CarouselSeeder skipped: site 1 missing.');

            return;
        }

        $items = [
            [
                'image_url' => 'https://cdn.example.com/mijing/carousel/site-1-banner-1.jpg',
                'link_url' => null,
                'sort_order' => 1,
            ],
            [
                'image_url' => 'https://cdn.example.com/mijing/carousel/site-1-banner-2.jpg',
                'link_url' => '/pages/appointmentCourse/index',
                'sort_order' => 2,
            ],
        ];

        foreach ($items as $item) {
            SiteCarouselItem::updateOrCreate(
                [
                    'tenant_id' => $site->tenant_id,
                    'site_id' => $site->id,
                    'image_url' => $item['image_url'],
                ],
                [
                    'link_url' => $item['link_url'],
                    'status' => 'published',
                    'sort_order' => $item['sort_order'],
                ],
            );
        }

        $this->command?->info('CarouselSeeder: site 1 carousel items seeded.');
    }
}
