<?php

namespace App\Services\Cards;

use App\Enums\CardType;
use App\Models\CardProduct;
use App\Models\CardProductCourseScope;
use App\Models\MemberCard;

class MemberCardBookingRulesPatchService
{
    public function patchStoredValueSnapshots(): int
    {
        $patched = 0;

        MemberCard::query()
            ->where('card_type', CardType::StoredValue)
            ->with(['cardProduct.courseScopes'])
            ->orderBy('id')
            ->chunkById(100, function ($cards) use (&$patched) {
                foreach ($cards as $card) {
                    if (! $this->snapshotMissingBookingPrice($card)) {
                        continue;
                    }

                    $product = $card->cardProduct;
                    if ($product === null) {
                        continue;
                    }

                    $snapshot = $this->buildProductSnapshot($product);
                    $card->update([
                        'product_snapshot' => array_merge(
                            $card->product_snapshot ?? [],
                            [
                                'cardType' => $snapshot['cardType'],
                                'bookingRules' => $snapshot['bookingRules'],
                                'courseScopes' => $snapshot['courseScopes'],
                            ],
                        ),
                    ]);
                    $patched++;
                }
            });

        return $patched;
    }

    private function snapshotMissingBookingPrice(MemberCard $card): bool
    {
        $snapshot = $card->product_snapshot ?? [];
        $cardType = $snapshot['cardType'] ?? $card->card_type?->value;

        if ($cardType !== CardType::StoredValue->value) {
            return false;
        }

        if (($snapshot['bookingRules']['defaultPrice'] ?? null) !== null
            && $snapshot['bookingRules']['defaultPrice'] !== '') {
            return false;
        }

        foreach ($snapshot['courseScopes'] ?? [] as $scope) {
            if (($scope['priceOverride'] ?? null) !== null && $scope['priceOverride'] !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProductSnapshot(CardProduct $product): array
    {
        return [
            'cardProductId' => $product->id,
            'cardType' => $product->card_type->value,
            'name' => $product->name,
            'price' => $this->decimalString($product->price),
            'faceValue' => $product->face_value !== null ? $this->decimalString($product->face_value) : null,
            'initialCount' => $product->initial_count,
            'validityDays' => $product->validity_days,
            'validityMode' => $product->validity_mode,
            'activationMode' => $product->activation_mode,
            'productVersion' => $product->version,
            'scopeConfig' => $product->scope_config,
            'bookingRules' => $product->booking_rules,
            'courseScopes' => $product->courseScopes
                ->sortBy('sort_order')
                ->values()
                ->map(fn (CardProductCourseScope $scope) => [
                    'scopeKind' => $scope->scope_kind->value,
                    'scopeKey' => $scope->scope_key,
                    'displayName' => $scope->display_name,
                    'priceOverride' => $scope->price_override !== null
                        ? $this->decimalString($scope->price_override)
                        : null,
                    'sortOrder' => $scope->sort_order,
                ])
                ->all(),
        ];
    }

    private function decimalString(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
