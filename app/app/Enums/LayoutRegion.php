<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * 公開側のレイアウトで部品を置く領域。
 */
enum LayoutRegion: int
{
    case Header = 1;
    case Sidebar = 2;
    case Footer = 3;

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::Header => __('ヘッダー'),
            self::Sidebar => __('サイドバー'),
            self::Footer => __('フッター'),
        };
    }

    /**
     * API でフロントエンドが領域を判別するための識別子(header / sidebar / footer)。
     */
    public function apiName(): string
    {
        return Str::snake($this->name);
    }
}
