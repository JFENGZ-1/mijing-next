<?php

namespace App\Services\Cards;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Models\CardProduct;
use App\Models\CardProductCourseScope;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class CardProductWriteService
{
    public function create(Staff $staff, Site $site, array $payload): CardProduct
    {
        return DB::transaction(function () use ($staff, $site, $payload) {
            $product = CardProduct::create([
                'tenant_id' => $staff->tenant_id,
                'site_id' => $site->id,
                'created_by_staff_id' => $staff->id,
                'catalog_status' => CardProductCatalogStatus::Active,
                'version' => 1,
                ...$this->productAttributes($payload),
            ]);

            $this->syncCourseScopes($product, $payload['courseScopes'] ?? []);

            return $product->load('courseScopes');
        });
    }

    public function update(CardProduct $product, array $payload): CardProduct
    {
        return DB::transaction(function () use ($product, $payload) {
            $updated = CardProduct::query()
                ->whereKey($product->id)
                ->where('tenant_id', $product->tenant_id)
                ->where('version', $payload['version'])
                ->update([
                    ...$this->productAttributes($payload, $product),
                    'version' => DB::raw('version + 1'),
                ]);

            abort_if($updated !== 1, 409, 'CARD_PRODUCT_VERSION_CONFLICT');

            if (array_key_exists('courseScopes', $payload)) {
                $this->syncCourseScopes($product->fresh(), $payload['courseScopes'] ?? []);
            }

            return $product->fresh()->load('courseScopes');
        });
    }

    public function archive(CardProduct $product): CardProduct
    {
        if ($product->catalog_status === CardProductCatalogStatus::Archived) {
            return $product;
        }

        $product->update([
            'catalog_status' => CardProductCatalogStatus::Archived,
            'archived_at' => now(),
        ]);

        return $product->fresh();
    }

    public function restore(CardProduct $product): CardProduct
    {
        if ($product->catalog_status === CardProductCatalogStatus::Active) {
            return $product;
        }

        $product->update([
            'catalog_status' => CardProductCatalogStatus::Active,
            'archived_at' => null,
        ]);

        return $product->fresh();
    }

    public function assertPhysicalDeleteForbidden(CardProduct $product): void
    {
        abort(409, 'CARD_PRODUCT_DELETE_FORBIDDEN');
    }

    private function productAttributes(array $payload, ?CardProduct $existing = null): array
    {
        $cardType = isset($payload['cardType'])
            ? CardType::from($payload['cardType'])
            : $existing?->card_type;

        return [
            'card_type' => $cardType,
            'name' => $payload['name'],
            'description' => $payload['description'] ?? null,
            'price' => $payload['price'],
            'face_value' => $payload['faceValue'] ?? null,
            'initial_count' => $payload['initialCount'] ?? null,
            'validity_days' => $payload['validityDays'] ?? null,
            'validity_mode' => $payload['validityMode'] ?? null,
            'activation_mode' => $payload['activationMode'] ?? ($existing?->activation_mode ?? 'immediate'),
            'scope_config' => $payload['scopeConfig'] ?? null,
            'booking_rules' => $payload['bookingRules'] ?? null,
            'sale_status' => isset($payload['saleStatus'])
                ? CardProductSaleStatus::from($payload['saleStatus'])
                : ($existing?->sale_status ?? CardProductSaleStatus::OnSale),
            'sort_order' => $payload['sortOrder'] ?? ($existing?->sort_order ?? 0),
        ];
    }

    private function syncCourseScopes(CardProduct $product, array $scopes): void
    {
        $product->courseScopes()->delete();

        foreach ($scopes as $index => $scope) {
            CardProductCourseScope::create([
                'tenant_id' => $product->tenant_id,
                'card_product_id' => $product->id,
                'scope_kind' => $scope['scopeKind'],
                'scope_key' => $scope['scopeKey'],
                'display_name' => $scope['displayName'] ?? null,
                'price_override' => $scope['priceOverride'] ?? null,
                'sort_order' => $scope['sortOrder'] ?? $index,
            ]);
        }
    }
}
