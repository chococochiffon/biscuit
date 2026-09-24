<?php

namespace App\Http\Controllers\API;

use App\Enums\ArticleApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\SinglePageResource;
use App\Models\Article;
use App\Models\SinglePage;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ResolveController extends Controller
{
    /**
     * 公開側のURLのパスから、表示するコンテンツ(固定ページまたは公開済みの記事)を取得する。
     * どちらも公開期間外(公開開始前・公開終了後)のものは該当なしとして扱う。
     * パスは記事・固定ページの間で重複しないよう保存時に検証しているため、先に見つかった方を返す。
     */
    #[OA\Get(
        path: '/resolve',
        summary: 'URLのパスから記事または固定ページを取得する',
        tags: ['Resolve'],
        parameters: [
            new OA\Parameter(name: 'path', in: 'query', required: true, description: '公開側URLのパス(例: /company/about、/news/123)', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'type(article または single_page)と data(記事または固定ページ)'),
            new OA\Response(response: 404, description: 'パスに該当するコンテンツがない、公開期間外、または記事が未公開'),
            new OA\Response(response: 422, description: 'path が未指定'),
        ]
    )]
    public function __invoke(Request $request): ArticleResource|SinglePageResource
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
        ]);

        $path = '/'.trim($validated['path'], '/');

        $singlePage = SinglePage::query()
            ->where('path', $path)
            ->withinPublicationPeriod()
            ->with('details')
            ->first();

        if ($singlePage !== null) {
            return (new SinglePageResource($singlePage))->additional(['type' => 'single_page']);
        }

        $article = Article::query()
            ->where('path', $path)
            ->where('approval', ArticleApprovalStatus::Published)
            ->withinPublicationPeriod()
            ->with(['user', 'tags'])
            ->firstOrFail();

        return (new ArticleResource($article))->additional(['type' => 'article']);
    }
}
