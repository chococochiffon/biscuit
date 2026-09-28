<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryCategoryResource;
use App\Models\GalleryCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class GalleryCategoryController extends Controller
{
    /**
     * ギャラリー画像の分類の一覧を並び順(sort_order、同順なら id)ですべて取得する(ギャラリーページの分類の切り替え用)。
     */
    #[OA\Get(
        path: '/gallery-categories',
        summary: 'ギャラリー画像の分類の一覧を並び順ですべて取得する',
        tags: ['Gallery'],
        responses: [
            new OA\Response(response: 200, description: '分類の一覧(並び順)。各要素は id・name'),
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        return GalleryCategoryResource::collection(GalleryCategory::query()->ordered()->get());
    }
}
