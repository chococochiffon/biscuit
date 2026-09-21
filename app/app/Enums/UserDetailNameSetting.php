<?php

namespace App\Enums;

enum UserDetailNameSetting: int
{
    case Hidden = 1;
    case FullName = 2;
    case NickName = 3;
    case FirstNameOnly = 4;

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::Hidden => __('非表示'),
            self::FullName => __('フルネーム'),
            self::NickName => __('ニックネーム'),
            self::FirstNameOnly => __('名前のみ'),
        };
    }
}
