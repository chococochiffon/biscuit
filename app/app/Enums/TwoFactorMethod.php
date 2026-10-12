<?php

namespace App\Enums;

/**
 * 二段階認証の方法。メールの確認コード(既定)か、Google Authenticator などの認証アプリ(TOTP)。
 */
enum TwoFactorMethod: string
{
    case Email = 'email';
    case Totp = 'totp';

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::Email => __('メールの確認コード'),
            self::Totp => __('認証アプリ(Google Authenticator)'),
        };
    }
}
