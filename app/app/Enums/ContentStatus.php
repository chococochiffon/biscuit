<?php

namespace App\Enums;

/**
 * 記事・固定ページの現在の状態(公開ステータスと公開期間を合わせたもの)。管理画面とマイページのダッシュボードで使う。
 * 記事の公開ステータスが「公開」のときは、公開期間によって公開中・予約公開・公開終了・非公開(公開開始日時の未設定)に分ける。
 */
enum ContentStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Ended = 'ended';
    case Unscheduled = 'unscheduled';

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => __('下書き'),
            self::Pending => __('未承認'),
            self::Published => __('公開中'),
            self::Scheduled => __('予約公開'),
            self::Ended => __('公開終了'),
            self::Unscheduled => __('非公開'),
        };
    }

    /**
     * バッジの色(Bootstrap の text-bg-*)。
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Published => 'success',
            self::Scheduled => 'info',
            self::Pending => 'warning',
            self::Draft, self::Ended, self::Unscheduled => 'secondary',
        };
    }
}
