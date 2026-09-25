<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiteSettingResource;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class SiteSettingController extends Controller
{
    /**
     * 登録済みのサイト設定を取得する。
     */
    #[OA\Get(
        path: '/site-setting',
        summary: '登録済みのサイト設定を取得する',
        tags: ['SiteSetting'],
        responses: [
            new OA\Response(response: 200, description: 'サイト設定(サイト名・説明・フロントのURL front_url・APIのURL api_url・アイコン/サイト画像のURLと、並び順のSNSリンク social_links(各要素は service(例: youtube)・name・url)・トップスライダー画像 top_slider_images(各要素は image_url(1920x1080)・url(リンク先、未設定は null)))'),
            new OA\Response(response: 404, description: 'サイト設定が未登録'),
        ]
    )]
    public function show(): SiteSettingResource|JsonResponse
    {
        $siteSetting = SiteSetting::query()->first();

        if (! $siteSetting) {
            return response()->json(['message' => 'Site setting not found.'], 404);
        }

        return new SiteSettingResource($siteSetting);
    }
}
