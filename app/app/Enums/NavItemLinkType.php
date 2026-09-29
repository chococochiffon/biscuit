<?php

namespace App\Enums;

/**
 * ナビメニューの項目のリンク先の種類。
 */
enum NavItemLinkType: int
{
    case Url = 1;
    case SinglePage = 2;
    case CustomPageType = 3;

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::Url => __('URL'),
            self::SinglePage => __('固定ページ'),
            self::CustomPageType => __('カスタムページの一覧'),
        };
    }
}
