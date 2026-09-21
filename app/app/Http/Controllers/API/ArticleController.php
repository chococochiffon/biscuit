<?php

namespace App\Http\Controllers\API;

use App\Enums\ArticleApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class ArticleController extends Controller
{
    /**
     * 公開済みの記事一覧を取得する。
     */
    #[OA\Get(
        path: '/articles',
        summary: '公開済みの記事一覧を取得する',
        tags: ['Articles'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'ページ番号', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: '記事一覧(ページネーション)'),
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        $articles = Article::query()
            ->where('approval', ArticleApprovalStatus::Published)
            ->with(['user', 'tags'])
            ->latest('created_at')
            ->paginate(20);

        return ArticleResource::collection($articles);
    }

    /**
     * 公開済みの記事詳細を取得する。
     */
    #[OA\Get(
        path: '/articles/{article}',
        summary: '公開済みの記事詳細を取得する',
        tags: ['Articles'],
        parameters: [
            new OA\Parameter(name: 'article', in: 'path', required: true, description: '記事ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: '記事詳細'),
            new OA\Response(response: 404, description: '記事が見つからない、または未公開'),
        ]
    )]
    public function show(Article $article): ArticleResource
    {
        abort_unless($article->approval === ArticleApprovalStatus::Published, 404);

        $article->load(['user', 'tags']);

        return new ArticleResource($article);
    }
}
