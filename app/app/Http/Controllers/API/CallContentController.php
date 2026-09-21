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
     * 指定した設置場所の呼び出しコンテンツ一覧を取得する(place未指定時はトップ扱い)。
     */
    #[OA\Get(
        path: '/call-contents',
        summary: '指定した設置場所の呼び出しコンテンツ一覧を取得する',
        tags: ['CallContents'],
        parameters: [
            new OA\Parameter(name: 'place', in: 'query', required: false, description: '設置場所(1:トップ, 2:本文内, 3:その他。未指定時は1)', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'ページ番号', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: '呼び出しコンテンツ一覧(ページネーション)'),
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
            ->where('place', $place)
            ->latest('created_at')
            ->paginate(20);

        return CallContentResource::collection($callContents);
    }
}
