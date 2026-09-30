<?php

namespace App\Support;

/**
 * バイト数を「1.5 MB」のような表示に整形する(Number::fileSize() は intl 拡張が必要なため使わない)。
 */
class FileSize
{
    private const UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

    public static function format(int|float $bytes, int $precision = 1): string
    {
        $unit = 0;

        while ($bytes >= 1024 && $unit < count(self::UNITS) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        return number_format($bytes, $unit === 0 ? 0 : $precision).' '.self::UNITS[$unit];
    }
}
