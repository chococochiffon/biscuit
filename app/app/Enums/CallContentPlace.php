<?php

namespace App\Enums;

enum CallContentPlace: int
{
    case Top = 1;
    case Inside = 2;
    case Others = 3;

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::Top => __('トップ'),
            self::Inside => __('本文内'),
            self::Others => __('その他'),
        };
    }
}
