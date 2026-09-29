<?php

namespace App\Enums;

enum CallContentPlace: int
{
    case Top = 1;
    case Inside = 2;
    /**
     * レイアウト管理の呼び出しコンテンツの部品(LayoutBlock)が使う設置場所。
     * サイト設定の呼び出しコンテンツでは選べない(もとの「その他」をレイアウトの部品に移した)。
     */
    case Layout = 3;

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::Top => __('トップ'),
            self::Inside => __('本文内'),
            self::Layout => __('レイアウトの部品'),
        };
    }

    /**
     * サイト設定の呼び出しコンテンツ(call_contents)で選べる設置場所。
     *
     * @return list<self>
     */
    public static function forCallContents(): array
    {
        return [self::Top, self::Inside];
    }
}
