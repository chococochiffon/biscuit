<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Article;
use App\Models\Tag;

/**
 * 記事の保存で、管理画面(ArticleController)と chococo のマイページ(API\MyArticleController)に共通する処理。
 */
trait SavesArticle
{
    /**
     * 監査ログの変更内容に、本体の列と並べて残す記事のタグ(タグ名をカンマ区切りで)。
     *
     * @return array{tags: string}
     */
    protected function auditTags(Article $article): array
    {
        return ['tags' => $article->tags->pluck('tag_name')->sort()->implode(', ')];
    }

    /**
     * タグ名の配列から未登録のタグを作成しつつ、記事とのタグ関連を同期する。
     *
     * @param  array<int, string>  $tagNames
     */
    protected function syncTags(Article $article, array $tagNames): void
    {
        $tagIds = collect($tagNames)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->map(fn ($name) => Tag::firstOrCreate(['tag_name' => $name])->id)
            ->all();

        $article->tags()->sync($tagIds);
    }
}
