<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\GalleryImageResource;
use App\Models\GalleryImage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class GalleryImageController extends Controller
{
    /**
     * ギャラリー画像の一覧を並び順(sort_order、同順なら id)ですべて取得する(ギャラリーページ用)。
     */
    #[OA\Get(
        path: '/gallery-images',
        summary: 'ギャラリー画像の一覧を並び順ですべて取得する',
        tags: ['Gallery'],
        responses: [
            new OA\Response(response: 200, description: 'ギャラリー画像の一覧(並び順)。各要素は id・name・comment・image_url と、category(分類 {id, name}。未分類なら null)'),
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        return GalleryImageResource::collection(GalleryImage::query()->with('category')->ordered()->get());
    }
}
