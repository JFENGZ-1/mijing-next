<?php

namespace App\Services\Cards;

/**
 * 会员卡卡面图案库（平台级）。
 * 当前来源为 config/card_faces.php；接入平台 Web 后台后改为读库，接口不变。
 */
class CardFaceLibraryService
{
    /**
     * @return list<array{id: int, name: string, gradient: string}>
     */
    public function items(): array
    {
        $items = config('card_faces.items', []);

        return collect(is_array($items) ? $items : [])
            ->map(fn ($item, $index) => [
                'id' => (int) ($item['id'] ?? $index),
                'name' => (string) ($item['name'] ?? '卡面'),
                'gradient' => (string) ($item['gradient'] ?? ''),
            ])
            ->filter(fn ($item) => $item['gradient'] !== '')
            ->values()
            ->all();
    }

    public function gradientFor(?int $faceStyle): ?string
    {
        if ($faceStyle === null || $faceStyle < 0) {
            return null;
        }
        foreach ($this->items() as $item) {
            if ($item['id'] === $faceStyle) {
                return $item['gradient'];
            }
        }

        return null;
    }
}
