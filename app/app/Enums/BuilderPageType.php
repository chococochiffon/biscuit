<?php

namespace App\Enums;

/**
 * ページビルダーで組み立てるページの種類。
 */
enum BuilderPageType: string
{
    case Top = 'top';
    case SinglePage = 'single_page';

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::Top => __('トップ'),
            self::SinglePage => __('固定ページ'),
        };
    }
}
