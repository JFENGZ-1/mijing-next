<?php

namespace App\Support;

/**
 * 姓名 → 拼音首字母（A-Z / #）。
 *
 * 分段边界按 GB2312 一级字库（拼音序，B0A1「啊」-20319 ~ D7F9「座」-10247）
 * 各字母段起点划分，并经 100 个常见姓氏样本校准（见 tmp 验证脚本与
 * MemberBookingPickerTest 的中文名断言）。
 */
final class PinyinInitial
{
    /**
     * 各字母段起点（code >= 起点 且 code < 下一行起点 → 归该字母）。
     * 负数编码：ord(gbk[0]) * 256 + ord(gbk[1]) - 65536。
     *
     * @var array<int, string>
     */
    private const SEGMENT_STARTS = [
        -20319 => 'A',
        -20283 => 'B',
        -19775 => 'C',
        -19219 => 'D',
        -18710 => 'E',
        -18526 => 'F',
        -18239 => 'G',
        -17923 => 'H',
        -17418 => 'J',
        -16475 => 'K',
        -16213 => 'L',
        -15641 => 'M',
        -15000 => 'N',
        -14930 => 'O',
        -14910 => 'P',
        -14550 => 'Q',
        -14240 => 'R',
        -14000 => 'S',
        -13200 => 'T',
        -12900 => 'W',
        -12450 => 'X',
        -11845 => 'Y',
        -11050 => 'Z',
    ];

    public static function fromName(?string $name): string
    {
        $trimmed = trim((string) $name);
        if ($trimmed === '') {
            return '#';
        }

        $first = mb_substr($trimmed, 0, 1, 'UTF-8');
        if (preg_match('/^[A-Za-z]$/', $first) === 1) {
            return strtoupper($first);
        }

        $gbk = @iconv('UTF-8', 'GBK//IGNORE', $first);
        if ($gbk === false || strlen($gbk) < 2) {
            return '#';
        }

        $code = (ord($gbk[0]) << 8) + ord($gbk[1]) - 65536;
        if ($code > 0 && $code < 160) {
            return strtoupper($first);
        }

        $result = '#';
        foreach (self::SEGMENT_STARTS as $start => $letter) {
            if ($code < $start) {
                break;
            }
            $result = $letter;
        }

        return $result;
    }
}
