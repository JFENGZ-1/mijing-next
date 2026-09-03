<?php

namespace App\Support\Dev;

final class MaskedOpenid
{
    public static function format(string $openid): string
    {
        $length = strlen($openid);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).substr($openid, -4);
    }
}
