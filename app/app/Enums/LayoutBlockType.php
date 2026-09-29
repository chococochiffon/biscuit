<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * レイアウトの領域に置く部品の種類。
 */
enum LayoutBlockType: int
{
    case SiteTitle = 1;
    case NavMenu = 2;
    case SocialLinks = 3;
    case FreeText = 4;
    case Copyright = 5;
    case CallContent = 6;

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::SiteTitle => __('サイトタイトル'),
            self::NavMenu => __('ナビメニュー'),
            self::SocialLinks => __('SNSリンク'),
            self::FreeText => __('自由テキスト'),
            self::Copyright => __('コピーライト'),
            self::CallContent => __('呼び出しコンテンツ'),
        };
    }

    /**
     * 見出し・小見出しを付けられる部品かどうか(サイトタイトルやコピーライトなどは見出しを持たない)。
     */
    public function hasHeading(): bool
    {
        return match ($this) {
            self::FreeText, self::CallContent => true,
            self::SiteTitle, self::NavMenu, self::SocialLinks, self::Copyright => false,
        };
    }

    /**
     * API でフロントエンドが部品を判別するための識別子(例: nav_menu)。
     */
    public function apiName(): string
    {
        return Str::snake($this->name);
    }
}
