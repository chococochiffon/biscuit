<?php

namespace App\Enums;

enum CallType: int
{
    case ShortSentence = 1;
    case OriginalText = 2;
    case LinkList = 3;
    case Link = 4;

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::ShortSentence => __('短文'),
            self::OriginalText => __('原文'),
            self::LinkList => __('リンクリスト'),
            self::Link => __('リンク'),
        };
    }
}
