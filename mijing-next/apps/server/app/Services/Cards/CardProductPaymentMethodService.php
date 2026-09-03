<?php

namespace App\Services\Cards;

use App\Models\CardProduct;
use App\Models\Site;
use App\Services\Catalog\CatalogCommandReceiptService;
use App\Support\DomainActor;
use Illuminate\Support\Facades\DB;

class CardProductPaymentMethodService
{
    public function __construct(private readonly CatalogCommandReceiptService $receipts) {}

    /**
     * @param  list<string>  $methods
     */
    public function update(
        DomainActor $actor,
        Site $site,
        CardProduct $product,
        array $methods,
        int $version,
        ?string $commandKey = null,
        ?string $reason = null,
    ): CardProduct {
        abort_unless(
            $product->tenant_id === $site->tenant_id && $product->site_id === $site->id,
            404,
        );

        $normalized = array_values(array_unique(array_map('strval', $methods)));
        sort($normalized);
        abort_if($normalized === [], 422, 'CARD_PRODUCT_PAYMENT_METHOD_REQUIRED');
        abort_if(array_diff($normalized, ['online', 'balance']) !== [], 422, 'CARD_PRODUCT_PAYMENT_METHOD_INVALID');

        $hash = $this->receipts->payloadHash([
            'productId' => $product->id, 'methods' => $normalized,
            'expectedVersion' => $version, 'reason' => $reason,
        ]);

        return DB::transaction(function () use ($actor, $site, $product, $normalized, $version, $commandKey, $reason, $hash) {
            $locked = CardProduct::query()->where('tenant_id', $site->tenant_id)
                ->where('site_id', $site->id)->whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($commandKey !== null && $this->receipts->replay(
                $site, 'card_product', 'payment_methods', $commandKey, $hash,
            ) !== null) {
                return $locked;
            }
            abort_unless($locked->version === $version, 409, 'CARD_PRODUCT_VERSION_CONFLICT');
            $locked->update([
                'allowed_payment_methods' => $normalized,
                'version' => $locked->version + 1,
            ]);
            if ($commandKey !== null) {
                $this->receipts->record(
                    $actor, $site, 'card_product', $locked->id, 'payment_methods',
                    $commandKey, $hash, $locked->version, $reason,
                );
            }

            return $locked->fresh();
        });
    }

    /** @return list<string> */
    public function methods(CardProduct $product): array
    {
        $methods = $product->allowed_payment_methods;

        return is_array($methods) && $methods !== [] ? array_values($methods) : ['online', 'balance'];
    }
}
