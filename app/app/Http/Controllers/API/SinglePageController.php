<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\SinglePageResource;
use App\Models\SinglePage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class SinglePageController extends Controller
{
    /**
     * 固定ページ一覧を取得する。
     */
    #[OA\Get(
        path: '/single-pages',
        summary: '固定ページ一覧を取得する',
        tags: ['SinglePages'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'ページ番号', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: '固定ページ一覧(ページネーション)'),
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        $singlePages = SinglePage::query()
            ->orderBy('title')
            ->paginate(20);

        return SinglePageResource::collection($singlePages);
    }

    /**
     * 固定ページ詳細を取得する。
     */
    #[OA\Get(
        path: '/single-pages/{singlePage}',
        summary: '固定ページ詳細(詳細セクション含む)を取得する',
        tags: ['SinglePages'],
        parameters: [
            new OA\Parameter(name: 'singlePage', in: 'path', required: true, description: '固定ページID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: '固定ページ詳細'),
            new OA\Response(response: 404, description: '固定ページが見つからない'),
        ]
    )]
    public function show(SinglePage $singlePage): SinglePageResource
    {
        $singlePage->load('details');

        return new SinglePageResource($singlePage);
    }
}
