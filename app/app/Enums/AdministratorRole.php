<?php

namespace App\Enums;

enum AdministratorRole: string
{
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => __('管理者'),
            self::SuperAdmin => __('スーパー管理者'),
        };
    }
}
