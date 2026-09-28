<?php

namespace App\Enums;

enum CustomPageBaseType: int
{
    case Article = 1;
    case SinglePage = 2;

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::Article => __('記事'),
            self::SinglePage => __('固定ページ'),
        };
    }

    /**
     * API でフロントエンドが表示を切り替えるための識別子(article / single_page)。
     */
    public function apiName(): string
    {
        return match ($this) {
            self::Article => 'article',
            self::SinglePage => 'single_page',
        };
    }
}
