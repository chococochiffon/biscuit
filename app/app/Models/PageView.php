<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PageViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * PV(公開側のページが表示された記録)の生データ。記録は Services\PageViewService、集計は Services\PageViewStatsService を使う。
 * 追記するだけで変更しないため、論理削除の方針の例外として SoftDeletes を使わない。
 */
#[Fillable(['content_type', 'content_id', 'path', 'user_id', 'visitor_id', 'session_id', 'ip_hash', 'user_agent', 'referer', 'viewed_at'])]
class PageView extends Model
{
    /** @use HasFactory<PageViewFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * 閲覧日時が期間内(両端を含む)のものに絞り込む。
     */
    #[Scope]
    protected function viewedBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereBetween('viewed_at', [$from, $to]);
    }

    /**
     * コンテンツの種類の表示名(例: article → 記事、custom_page:recipe → カスタムページ(recipe))。
     */
    public static function labelForContentType(string $contentType): string
    {
        if (str_starts_with($contentType, 'custom_page_list:')) {
            return __('カスタムページの一覧(:name)', ['name' => Str::after($contentType, 'custom_page_list:')]);
        }

        if (str_starts_with($contentType, 'custom_page:')) {
            return __('カスタムページ(:name)', ['name' => Str::after($contentType, 'custom_page:')]);
        }

        return match ($contentType) {
            'top' => __('トップ'),
            'article' => __('記事'),
            'single_page' => __('固定ページ'),
            default => $contentType,
        };
    }
}
