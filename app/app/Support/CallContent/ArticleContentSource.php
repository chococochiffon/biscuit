<?php

namespace App\Support\CallContent;

use App\Models\Article;
use Illuminate\Database\Eloquent\Collection;

/**
 * 呼び出しコンテンツ(CallContent)がArticleを参照する場合の実データ取得を担う。
 */
class ArticleContentSource
{
    /**
     * 原文表示用に、最新(公開開始日時が最も新しい)の公開済み記事を1件取得する。
     */
    public function getOriginalText(): ?Article
    {
        return Article::query()->published()->with('user.detail')->newest()->first();
    }

    /**
     * リンクリスト表示用に、最新の公開済み記事を指定件数取得する。
     *
     * @return Collection<int, Article>
     */
    public function getLinkList(int $count): Collection
    {
        return Article::query()->published()->with('user.detail')->newest()->take($count)->get();
    }

    /**
     * リンク表示用に、最新の公開済み記事を1件取得する。
     */
    public function getLink(): ?Article
    {
        return Article::query()->published()->with('user.detail')->newest()->first();
    }

    /**
     * アーカイブ表示用に、最新の公開済み記事を指定件数取得する。
     *
     * @return Collection<int, Article>
     */
    public function getArchive(int $count): Collection
    {
        return Article::query()->published()->with('user.detail')->newest()->take($count)->get();
    }
}
