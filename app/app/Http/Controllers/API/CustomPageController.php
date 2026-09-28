<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomPageEntryResource;
use App\Models\CustomPages\CustomPageEntry;
use App\Models\CustomPageType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class CustomPageController extends Controller
{
    /**
     * 種類ごとの公開中のカスタムページの一覧を、ページネーションで取得する(種類ごとの一覧ページ用)。
     * 記事型は公開ステータスが「公開」かつ公開期間内を公開開始日時の新しい順、固定ページ型は公開期間内を表示順で返す。
     */
    #[OA\Get(
        path: '/custom-pages/{name}',
        summary: 'カスタムページの一覧を種類ごとに取得する',
        tags: ['CustomPages'],
        parameters: [
            new OA\Parameter(name: 'name', in: 'path', required: true, description: 'カスタム名(例: recipe)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'ページ番号', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'カスタムページの一覧(ページネーション)。記事型は記事、固定ページ型は固定ページと同じ項目名で、custom_page_type に種類を含む'),
            new OA\Response(response: 404, description: 'カスタム名に該当する種類がない'),
        ]
    )]
    public function index(CustomPageType $customPageType): AnonymousResourceCollection
    {
        return CustomPageEntryResource::collection(
            CustomPageEntry::publishedQueryFor($customPageType)->paginate(config('limits.api_per_page'))
        );
    }
}
