<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * レイアウトのサイドバーの位置。
 */
enum SidebarPosition: int
{
    case None = 1;
    case Left = 2;
    case Right = 3;

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::None => __('なし'),
            self::Left => __('左'),
            self::Right => __('右'),
        };
    }

    /**
     * API でフロントエンドがサイドバーの位置を判別するための識別子(none / left / right)。
     */
    public function apiName(): string
    {
        return Str::snake($this->name);
    }
}
