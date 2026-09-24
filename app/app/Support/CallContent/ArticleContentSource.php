<?php

namespace App\Support\CallContent;

use App\Enums\ArticleApprovalStatus;
use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * 呼び出しコンテンツ(CallContent)がArticleを参照する場合の実データ取得を担う。
 */
class ArticleContentSource
{
    /**
     * 原文表示用に、最新の公開済み記事を1件取得する。
     */
    public function getOriginalText(): ?Article
    {
        return $this->publishedQuery()->latest()->first();
    }

    /**
     * リンクリスト表示用に、最新の公開済み記事を指定件数取得する。
     *
     * @return Collection<int, Article>
     */
    public function getLinkList(int $count): Collection
    {
        return $this->publishedQuery()->latest()->take($count)->get();
    }

    /**
     * リンク表示用に、最新の公開済み記事を1件取得する。
     */
    public function getLink(): ?Article
    {
        return $this->publishedQuery()->latest()->first();
    }

    /**
     * アーカイブ表示用に、最新の公開済み記事を指定件数取得する。
     *
     * @return Collection<int, Article>
     */
    public function getArchive(int $count): Collection
    {
        return $this->publishedQuery()->latest()->take($count)->get();
    }

    /**
     * 公開済み(approval=Published)かつ公開期間内の記事のクエリ。
     *
     * @return Builder<Article>
     */
    private function publishedQuery(): Builder
    {
        return Article::query()
            ->where('approval', ArticleApprovalStatus::Published)
            ->withinPublicationPeriod();
    }
}
