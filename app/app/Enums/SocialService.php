<?php

namespace App\Enums;

enum SocialService: int
{
    case X = 1;
    case YouTube = 2;
    case GitHub = 3;
    case Instagram = 4;
    case Facebook = 5;
    case TikTok = 6;
    case Twitch = 7;
    case Discord = 8;
    case Threads = 9;
    case Amazon = 10;
    case Other = 11;

    /**
     * 表示用のラベルを取得する(サービス名はそのまま、その他のみ現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::X => 'X',
            self::YouTube => 'YouTube',
            self::GitHub => 'GitHub',
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::TikTok => 'TikTok',
            self::Twitch => 'Twitch',
            self::Discord => 'Discord',
            self::Threads => 'Threads',
            self::Amazon => 'Amazon',
            self::Other => __('その他'),
        };
    }

    /**
     * APIレスポンスでフロントエンドがアイコンを出し分けるための識別子(例: youtube)。
     */
    public function apiName(): string
    {
        return strtolower($this->name);
    }
}
