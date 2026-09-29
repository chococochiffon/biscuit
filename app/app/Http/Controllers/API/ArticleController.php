<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class ArticleController extends Controller
{
    /**
     * 公開済みかつ公開期間内の記事一覧を、新しい順にページネーションで取得する(記事一覧ページのページ送り用)。
     * 記事1件の本文はパス解決API(/api/resolve)で取得する。author を指定すると、そのユーザーが投稿した記事だけにする(投稿者ページ用)。
     */
    #[OA\Get(
        path: '/articles',
        summary: '公開済みかつ公開期間内の記事一覧を、公開開始日時の新しい順に取得する',
        tags: ['Articles'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'ページ番号', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'author', in: 'query', required: false, description: '投稿者(ユーザーの id)で絞り込む', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: '記事一覧(ページネーション)'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $authorId = $request->integer('author');

        $articles = Article::query()
            ->published()
            ->when($authorId > 0, fn ($query) => $query->where('user_id', $authorId))
            ->with(['user.detail', 'tags'])
            ->newest()
            ->paginate(config('limits.api_per_page'));

        return ArticleResource::collection($articles);
    }
}
