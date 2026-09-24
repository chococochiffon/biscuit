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
     * 公開済みかつ公開期間内の記事一覧を、新しい順にページネーションで取得する(記事一覧ページのページ送り用)。
     * 記事1件の本文はパス解決API(/api/resolve)で取得する。
     */
    #[OA\Get(
        path: '/articles',
        summary: '公開済みかつ公開期間内の記事一覧を取得する',
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
            ->withinPublicationPeriod()
            ->with(['user', 'tags'])
            ->latest('created_at')
            ->paginate(20);

        return ArticleResource::collection($articles);
    }
}
