<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomPageTypeResource;
use App\Models\CustomPageType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class CustomPageTypeController extends Controller
{
    /**
     * カスタムページの種類の一覧を並び順で取得する(ナビに種類ごとの一覧へのリンクを並べる用)。
     */
    #[OA\Get(
        path: '/custom-page-types',
        summary: 'カスタムページの種類の一覧を並び順で取得する',
        tags: ['CustomPages'],
        responses: [
            new OA\Response(response: 200, description: '種類の一覧(並び順)。各要素は name・label・base_type(article/single_page)・path(一覧の URL。例: /recipes)'),
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        return CustomPageTypeResource::collection(CustomPageType::query()->ordered()->get());
    }
}
