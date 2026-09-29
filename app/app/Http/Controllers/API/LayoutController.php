<?php

namespace App\Http\Controllers\API;

use App\Enums\LayoutPageType;
use App\Enums\LayoutRegion;
use App\Http\Controllers\Controller;
use App\Http\Resources\LayoutBlockResource;
use App\Models\Layout;
use App\Models\LayoutBlock;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class LayoutController extends Controller
{
    /**
     * 公開側のレイアウト(ページの種類ごとのサイドバーの位置と、ヘッダー・サイドバー・フッターに置く部品)をまとめて取得する。
     * ページの種類はパス解決 API の type から決める(カスタムページは記事型を article・固定ページ型を single_page、一覧などは other)。
     */
    #[OA\Get(
        path: '/layout',
        summary: '公開側のレイアウト(サイドバーの位置と領域ごとの部品)を取得する',
        tags: ['Layout'],
        responses: [
            new OA\Response(response: 200, description: 'data.pages: ページの種類(top/article/single_page/other)ごとの sidebar_position(none/left/right)。data.regions: 領域(header/sidebar/footer)ごとの部品の一覧(並び順)。各部品は block_type(site_title/nav_menu/social_links/free_text/copyright/call_content)・title・subtitle と、nav_menu は items(label・path・prefix)、free_text は content(HTML)、call_content は call_content(呼び出しコンテンツ API の 1 要素と同じ形)'),
        ]
    )]
    public function show(): JsonResponse
    {
        $sidebarPositions = Layout::sidebarPositions();
        $blocks = LayoutBlock::query()
            ->with([
                'contentModelRelation',
                'navItems' => fn ($query) => $query->ordered(),
                // 公開期間外の固定ページへの項目はナビに出さない
                'navItems.singlePage' => fn ($query) => $query->published(),
                'navItems.customPageType',
            ])
            ->ordered()
            ->get()
            ->groupBy(fn (LayoutBlock $block) => $block->region->value);

        return response()->json([
            'data' => [
                'pages' => collect(LayoutPageType::cases())
                    ->mapWithKeys(fn (LayoutPageType $pageType) => [
                        $pageType->apiName() => ['sidebar_position' => $sidebarPositions[$pageType->value]->apiName()],
                    ]),
                'regions' => collect(LayoutRegion::cases())
                    ->mapWithKeys(fn (LayoutRegion $region) => [
                        $region->apiName() => LayoutBlockResource::collection($blocks->get($region->value, collect())),
                    ]),
            ],
        ]);
    }
}
