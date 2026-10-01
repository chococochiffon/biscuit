<?php

namespace App\Support\Builder;

use App\Models\Article;
use Illuminate\Database\Eloquent\Collection;

/**
 * 記事一覧のブロック(article-list)の取得の条件から、公開中の記事を取得する。
 * 公開側(BuilderPresenter::forPublic())と、管理画面のエディタの Canvas の見本(PageBuilderJsonController::articleList())で共通。
 */
final class ArticleListQuery
{
    /**
     * 条件どおりの公開中の記事(投稿者・タグ付き)。
     *
     * @param  array<string, mixed>  $props  ブロックの props(limit・order・parentPath・tag)
     * @return Collection<int, Article>
     */
    public static function articles(array $props): Collection
    {
        $limit = is_int($props['limit'] ?? null) ? max(1, min(20, $props['limit'])) : 6;
        $parentPath = trim(is_string($props['parentPath'] ?? null) ? $props['parentPath'] : '', '/ ');
        $tag = trim(is_string($props['tag'] ?? null) ? $props['tag'] : '');

        return Article::query()
            ->published()
            ->with(['user.detail', 'tags'])
            ->when($parentPath !== '', fn ($query) => $query->where('parent_path', $parentPath))
            ->when($tag !== '', fn ($query) => $query->whereHas('tags', fn ($tags) => $tags->where('tag_name', $tag)))
            ->when(
                ($props['order'] ?? 'newest') === 'oldest',
                fn ($query) => $query->orderBy('publication_start_datetime')->orderBy('id'),
                fn ($query) => $query->newest(),
            )
            ->limit($limit)
            ->get();
    }
}
