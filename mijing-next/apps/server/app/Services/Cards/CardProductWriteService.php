<?php

namespace App\Services\Cards;

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Models\CardProduct;
use App\Models\CardProductCourseScope;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Catalog\CatalogCommandReceiptService;
use App\Support\DomainActor;
use Illuminate\Support\Facades\DB;

class CardProductWriteService
{
    public function __construct(private CatalogCommandReceiptService $commands) {}

    public function create(Staff $staff, Site $site, array $payload): CardProduct
    {
        return $this->createForActor(DomainActor::staff($staff), $site, $payload);
    }

    public function createForActor(DomainActor $actor, Site $site, array $payload): CardProduct
    {
        return DB::transaction(function () use ($actor, $site, $payload) {
            Site::query()->where('tenant_id', $site->tenant_id)->whereKey($site->id)->lockForUpdate()->firstOrFail();
            $commandKey = $payload['commandKey'] ?? null;
            $payloadHash = $this->commands->payloadHash($payload);
            if ($commandKey !== null) {
                $receipt = $this->commands->replay($site, 'card_product', 'create', $commandKey, $payloadHash);
                if ($receipt) {
                    return CardProduct::query()
                        ->where('tenant_id', $site->tenant_id)
                        ->where('site_id', $site->id)
                        ->findOrFail($receipt->resource_id)
                        ->load('courseScopes');
                }
            }
            $product = CardProduct::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'created_by_staff_id' => $actor->staffId(),
                'catalog_status' => CardProductCatalogStatus::Active,
                'version' => 1,
                ...$this->productAttributes($payload),
            ]);

            $this->syncCourseScopes($product, $payload['courseScopes'] ?? []);
            if ($commandKey !== null) {
                $this->commands->record(
                    $actor, $site, 'card_product', $product->id, 'create', $commandKey,
                    $payloadHash, $product->version, $payload['reason'] ?? null,
                );
            }

            return $product->load('courseScopes');
        });
    }

    public function update(CardProduct $product, array $payload): CardProduct
    {
        $site = Site::query()->where('tenant_id', $product->tenant_id)->findOrFail($product->site_id);

        return $this->updateForActor(DomainActor::system(), $site, $product, $payload);
    }

    public function updateForActor(
        DomainActor $actor,
        Site $site,
        CardProduct $product,
        array $payload,
    ): CardProduct {
        abort_unless($product->tenant_id === $site->tenant_id && $product->site_id === $site->id, 404);

        return DB::transaction(function () use ($actor, $site, $product, $payload) {
            $locked = CardProduct::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('site_id', $site->id)
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();
            $commandKey = $payload['commandKey'] ?? null;
            $payloadHash = $this->commands->payloadHash($payload);
            if ($commandKey !== null) {
                $receipt = $this->commands->replay($site, 'card_product', 'update', $commandKey, $payloadHash);
                if ($receipt) {
                    abort_unless((int) $receipt->resource_id === $locked->id, 409, 'IDEMPOTENCY_KEY_REUSED');

                    return $locked->fresh()->load('courseScopes');
                }
            }
            if (array_key_exists('cardType', $payload)) {
                abort_unless($payload['cardType'] === $locked->card_type->value, 422, 'CARD_PRODUCT_TYPE_IMMUTABLE');
            }
            $updated = CardProduct::query()
                ->whereKey($locked->id)
                ->where('tenant_id', $locked->tenant_id)
                ->where('version', $payload['version'])
                ->update([
                    ...$this->productAttributes($payload, $locked),
                    'version' => DB::raw('version + 1'),
                ]);

            abort_if($updated !== 1, 409, 'CARD_PRODUCT_VERSION_CONFLICT');

            if (array_key_exists('courseScopes', $payload)) {
                $this->syncCourseScopes($locked->fresh(), $payload['courseScopes'] ?? []);
            }

            $fresh = $locked->fresh()->load('courseScopes');
            if ($commandKey !== null) {
                $this->commands->record(
                    $actor, $site, 'card_product', $fresh->id, 'update', $commandKey,
                    $payloadHash, $fresh->version, $payload['reason'] ?? null,
                );
            }

            return $fresh;
        });
    }

    public function archive(CardProduct $product): CardProduct
    {
        $site = Site::query()->where('tenant_id', $product->tenant_id)->findOrFail($product->site_id);

        return $this->archiveForActor(DomainActor::system(), $site, $product, $product->version);
    }

    public function archiveForActor(
        DomainActor $actor,
        Site $site,
        CardProduct $product,
        int $expectedVersion,
        ?string $commandKey = null,
        ?string $reason = null,
    ): CardProduct {
        return $this->changeStatus(
            $actor, $site, $product, CardProductCatalogStatus::Archived,
            $expectedVersion, $commandKey, $reason,
        );
    }

    public function restore(CardProduct $product): CardProduct
    {
        $site = Site::query()->where('tenant_id', $product->tenant_id)->findOrFail($product->site_id);

        return $this->restoreForActor(DomainActor::system(), $site, $product, $product->version);
    }

    public function restoreForActor(
        DomainActor $actor,
        Site $site,
        CardProduct $product,
        int $expectedVersion,
        ?string $commandKey = null,
        ?string $reason = null,
    ): CardProduct {
        return $this->changeStatus(
            $actor, $site, $product, CardProductCatalogStatus::Active,
            $expectedVersion, $commandKey, $reason,
        );
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
            'description' => array_key_exists('description', $payload)
                ? $payload['description']
                : $existing?->description,
            'price' => $payload['price'],
            'face_value' => array_key_exists('faceValue', $payload)
                ? $payload['faceValue']
                : $existing?->face_value,
            'initial_count' => array_key_exists('initialCount', $payload)
                ? $payload['initialCount']
                : $existing?->initial_count,
            'validity_days' => array_key_exists('validityDays', $payload)
                ? $payload['validityDays']
                : $existing?->validity_days,
            'validity_mode' => array_key_exists('validityMode', $payload)
                ? $payload['validityMode']
                : $existing?->validity_mode,
            'activation_mode' => $payload['activationMode'] ?? ($existing?->activation_mode ?? 'immediate'),
            'allowed_payment_methods' => $payload['allowedPaymentMethods']
                ?? $existing?->allowed_payment_methods
                ?? ['online', 'balance'],
            'scope_config' => array_key_exists('scopeConfig', $payload)
                ? $payload['scopeConfig']
                : $existing?->scope_config,
            'booking_rules' => array_key_exists('bookingRules', $payload)
                ? $payload['bookingRules']
                : $existing?->booking_rules,
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

    private function changeStatus(
        DomainActor $actor,
        Site $site,
        CardProduct $product,
        CardProductCatalogStatus $status,
        int $expectedVersion,
        ?string $commandKey,
        ?string $reason,
    ): CardProduct {
        abort_unless($product->tenant_id === $site->tenant_id && $product->site_id === $site->id, 404);

        return DB::transaction(function () use ($actor, $site, $product, $status, $expectedVersion, $commandKey, $reason) {
            $locked = CardProduct::query()->where('tenant_id', $site->tenant_id)->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $action = $status === CardProductCatalogStatus::Archived ? 'archive' : 'restore';
            $payloadHash = $this->commands->payloadHash(['expectedVersion' => $expectedVersion, 'reason' => $reason]);
            if ($commandKey !== null) {
                $receipt = $this->commands->replay($site, 'card_product', $action, $commandKey, $payloadHash);
                if ($receipt) {
                    abort_unless((int) $receipt->resource_id === $locked->id, 409, 'IDEMPOTENCY_KEY_REUSED');

                    return $locked;
                }
            }
            if ($locked->catalog_status === $status) {
                abort_if($commandKey !== null, 409, 'CATALOG_STATUS_ALREADY_CHANGED');

                return $locked;
            }
            $updated = CardProduct::query()
                ->whereKey($locked->id)
                ->where('version', $expectedVersion)
                ->update([
                    'catalog_status' => $status,
                    'archived_at' => $status === CardProductCatalogStatus::Archived ? now() : null,
                    'version' => DB::raw('version + 1'),
                ]);
            abort_unless($updated === 1, 409, 'CARD_PRODUCT_VERSION_CONFLICT');
            $fresh = $locked->fresh();
            if ($commandKey !== null) {
                $this->commands->record(
                    $actor, $site, 'card_product', $fresh->id, $action, $commandKey,
                    $payloadHash, $fresh->version, $reason,
                );
            }

            return $fresh;
        });
    }
}
