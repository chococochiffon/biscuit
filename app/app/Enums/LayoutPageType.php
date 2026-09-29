<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * レイアウト(サイドバーの位置)を切り替えるページの種類。
 * カスタムページは記事型を記事・固定ページ型を固定ページとして扱い、一覧などそれ以外のページはその他にする。
 */
enum LayoutPageType: int
{
    case Top = 1;
    case Article = 2;
    case SinglePage = 3;
    case Other = 4;

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::Top => __('トップ'),
            self::Article => __('記事'),
            self::SinglePage => __('固定ページ'),
            self::Other => __('その他のページ(一覧など)'),
        };
    }

    /**
     * API でフロントエンドがページの種類を判別するための識別子(top / article / single_page / other)。
     */
    public function apiName(): string
    {
        return Str::snake($this->name);
    }
}
