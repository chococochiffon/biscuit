<?php

namespace App\Support;

use App\Enums\CallContentPlace;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\CallContentResource;
use App\Http\Resources\SinglePageResource;
use App\Models\Article;
use App\Models\CallContent;
use App\Models\PageBuilder;
use App\Models\SinglePage;
use App\Models\SiteSetting;
use App\Support\Builder\BuilderPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * 公開側のページ(トップ・固定ページ・記事)の表示に使う内容を、パス解決 API(API\ResolveController)と
 * ページビルダーのプレビュー API(API\BuilderPreviewController)で共通の形に組み立てる。
 * ページビルダーの内容(builder)は、トップはサイト設定の top_use_builder、固定ページは use_builder が true で公開済みのときだけ返す。
 * プレビューでは、公開中の内容の代わりに編集中の内容を渡す。
 */
class PublicPageResponder
{
    /**
     * トップ。ページビルダーの内容を渡さなければ、公開中の内容(使わない設定・未公開なら null)を返す。
     *
     * @param  array<string, mixed>|null  $builderContent
     */
    public function top(?array $builderContent = null): JsonResponse
    {
        if ($builderContent === null && SiteSetting::current()?->top_use_builder) {
            $builderContent = PageBuilder::top()?->published_content;
        }

        return response()->json([
            'type' => 'top',
            'data' => null,
            'builder' => $builderContent === null ? null : BuilderPresenter::forPublic($builderContent),
            'breadcrumbs' => [],
            'call_contents' => $this->callContents(CallContentPlace::Top),
        ]);
    }

    /**
     * 固定ページ・記事の本文。固定ページは、ページビルダーの内容を渡さなければ公開中の内容(使わない設定・未公開なら null)を data.builder に入れる。
     *
     * @param  array<string, mixed>|null  $builderContent
     */
    public function pageContent(Article|SinglePage $pageContent, ?array $builderContent = null): JsonResponse
    {
        $pageContent->load($pageContent instanceof Article ? ['user.detail', 'tags'] : ['details', 'builder']);

        if ($pageContent instanceof SinglePage && $builderContent === null && $pageContent->use_builder) {
            $builderContent = $pageContent->builder?->published_content;
        }

        return response()->json([
            'type' => $pageContent instanceof Article ? 'article' : 'single_page',
            'data' => $pageContent instanceof Article
                ? new ArticleResource($pageContent)
                : (new SinglePageResource($pageContent))->withBuilder($builderContent === null ? null : BuilderPresenter::forPublic($builderContent)),
            'breadcrumbs' => Breadcrumbs::forPage($pageContent->path, $pageContent->title),
            'call_contents' => $this->callContents(CallContentPlace::Inside, $pageContent),
        ]);
    }

    /**
     * 設置場所の呼び出しコンテンツを並び順で整形する。ページの本文を渡した場合は、そのページに表示しない枠を除く。
     *
     * @return Collection<int, CallContentResource>
     */
    public function callContents(CallContentPlace $place, ?Model $pageContent = null): Collection
    {
        $resolver = new CallContentResolver;

        return CallContent::query()
            ->with('contentModelRelation')
            ->forPlace($place)
            ->get()
            ->filter(fn (CallContent $callContent) => $pageContent === null || $resolver->appliesToPage($callContent, $pageContent))
            ->values()
            ->map(fn (CallContent $callContent) => (new CallContentResource($callContent))->withPageContent($pageContent));
    }
}
