<?php

namespace App\Http\Controllers\API;

use App\Enums\CallContentPlace;
use App\Http\Controllers\Controller;
use App\Http\Resources\CallContentResource;
use App\Models\CallContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class CallContentController extends Controller
{
    /**
     * 指定した設置場所の呼び出しコンテンツ一覧を、並び順(sort_order)で取得する(place未指定時はトップ扱い)。
     * 各要素は call_type・call_name・title・subtitle と、table_name をキーにした実データを持つ。
     * ページ単位の表示(トップ・本文ページ)にはパス解決API(/api/resolve)を使い、ここはヘッダー・フッターなど
     * 共通部品(その他)の取得に使う想定。
     */
    #[OA\Get(
        path: '/call-contents',
        summary: '指定した設置場所の呼び出しコンテンツ一覧を取得する',
        tags: ['CallContents'],
        parameters: [
            new OA\Parameter(name: 'place', in: 'query', required: false, description: '設置場所(1:トップ, 2:本文内, 3:その他。未指定時は1)', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: '呼び出しコンテンツ一覧(並び順)。各要素は call_type(例: link_list)・call_name(管理用ラベル)・title/subtitle(公開側の見出し・小見出し。未設定はnull)と、table_name(articles/single_pages/user_details)をキーにした実データ'),
            new OA\Response(response: 422, description: 'placeの値が不正'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'place' => ['sometimes', 'integer', Rule::enum(CallContentPlace::class)],
        ]);

        $place = isset($validated['place'])
            ? CallContentPlace::from($validated['place'])
            : CallContentPlace::Top;

        $callContents = CallContent::query()
            ->with('contentModelRelation')
            ->forPlace($place)
            ->get();

        return CallContentResource::collection($callContents);
    }
}
