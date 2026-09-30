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
     * 公開中のギャラリー画像の一覧を並び順(sort_order、同順なら id)ですべて取得する(ギャラリーページ用)。
     * ユーザーがマイページから投稿した画像は、承認されて公開になるまで出さない。
     */
    #[OA\Get(
        path: '/gallery-images',
        summary: '公開中のギャラリー画像の一覧を並び順ですべて取得する(承認前のユーザーの投稿は出さない)',
        tags: ['Gallery'],
        responses: [
            new OA\Response(response: 200, description: 'ギャラリー画像の一覧(並び順)。各要素は id・name・comment・image_url と、category(分類 {id, name}。未分類なら null)'),
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        return GalleryImageResource::collection(GalleryImage::query()->published()->with('category')->ordered()->get());
    }
}
