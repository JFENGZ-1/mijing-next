<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class AvatarUrl
{
    /**
     * 会员头像 objectKey 转访问 URL（兼容已存完整 URL 的历史数据）。
     */
    public static function fromObjectKey(?string $objectKey): ?string
    {
        if (! $objectKey) {
            return null;
        }
        if (str_starts_with($objectKey, 'http://') || str_starts_with($objectKey, 'https://')) {
            return $objectKey;
        }

        return Storage::disk('public')->url($objectKey);
    }
}
