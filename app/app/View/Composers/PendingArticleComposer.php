<?php

namespace App\View\Composers;

use App\Enums\ArticleApprovalStatus;
use App\Models\Article;
use Illuminate\View\View;

class PendingArticleComposer
{
    /**
     * 承認待ち(未承認)の記事の件数をビューへ共有する(サイドメニューと記事一覧のバッジに使う)。
     */
    public function compose(View $view): void
    {
        $view->with('pendingArticleCount', Article::query()->withApproval(ArticleApprovalStatus::Pending)->count());
    }
}
