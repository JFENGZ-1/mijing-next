<?php

namespace Database\Seeders;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Models\CardProduct;
use App\Models\Site;
use Illuminate\Database\Seeder;

class CardProductSeeder extends Seeder
{
    public function run(): void
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            return;
        }

        $sites = Site::query()->where('status', 'active')->orderBy('id')->get();
        if ($sites->isEmpty()) {
            $this->command?->warn('CardProductSeeder skipped: no active sites.');

            return;
        }

        foreach ($sites as $site) {
            $this->seedSiteCatalog($site);
        }

        $this->command?->info('CardProductSeeder: sample catalog products ready for '.$sites->count().' site(s).');
    }

    private function seedSiteCatalog(Site $site): void
    {
        $storedValue = CardProduct::query()->firstOrCreate(
            ['tenant_id' => $site->tenant_id, 'site_id' => $site->id, 'name' => '储值卡 1000'],
            [
                'card_type' => CardType::StoredValue,
                'description' => '本地演示储值卡模板',
                'price' => 1000,
                'face_value' => 1000,
                'booking_rules' => ['defaultPrice' => '88.00'],
                'sale_status' => CardProductSaleStatus::OnSale,
                'catalog_status' => CardProductCatalogStatus::Active,
                'sort_order' => 10,
                'version' => 1,
            ],
        );

        if (($storedValue->booking_rules['defaultPrice'] ?? null) === null) {
            $storedValue->update([
                'booking_rules' => array_merge($storedValue->booking_rules ?? [], [
                    'defaultPrice' => '88.00',
                ]),
            ]);
        }

        CardProduct::query()->firstOrCreate(
            ['tenant_id' => $site->tenant_id, 'site_id' => $site->id, 'name' => '瑜伽 10 次卡'],
            [
                'card_type' => CardType::Count,
                'description' => '本地演示计次卡模板',
                'price' => 880,
                'initial_count' => 10,
                'validity_days' => 180,
                'validity_mode' => 'from_activation',
                'activation_mode' => 'on_first_use',
                'sale_status' => CardProductSaleStatus::OnSale,
                'catalog_status' => CardProductCatalogStatus::Active,
                'sort_order' => 20,
                'version' => 1,
            ],
        );
    }
}
