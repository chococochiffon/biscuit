<?php

namespace App\Enums;

enum ArticleApprovalStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'published';

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => __('下書き'),
            self::Pending => __('未承認'),
            self::Published => __('公開'),
        };
    }
}
