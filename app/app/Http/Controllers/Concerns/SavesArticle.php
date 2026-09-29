<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\AuditAction;
use App\Models\Article;
use App\Models\Tag;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    /**
     * 本文のリッチテキストエディタから送られた画像(image)を保存し、本文の img の src に使う URL を返す。
     */
    protected function storeContentImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:'.config('limits.image_max_kilobytes')],
        ]);

        $path = $request->file('image')->store(Article::CONTENT_IMAGE_DIRECTORY, 'public');
        AuditLogger::record(AuditAction::Uploaded, 'article_content_image', label: $path);

        return response()->json(['url' => Article::publicImageUrl($path)]);
    }
}
