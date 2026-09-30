<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PageViewStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class MyPageViewController extends Controller
{
    /**
     * 日別の推移と人気記事を集計する日数(今日を含む直近の日数)。
     */
    public const DAYS = 30;

    /**
     * 人気記事の件数。
     */
    public const RANKING_LIMIT = 10;

    /**
     * マイページのダッシュボード用に、ログイン中のユーザーの記事(論理削除したものを除く)のアクセスを集計する。
     * サイト全体の数字は返さない。
     */
    #[OA\Get(
        path: '/me/page-views',
        summary: '自分の記事のアクセス(PV・UU)を取得する',
        security: [['bearer' => []]],
        tags: ['Me'],
        responses: [
            new OA\Response(response: 200, description: 'data.summary(today・yesterday・this_month・total ごとの views・unique_visitors)、data.daily(直近 30 日の date・views・unique_visitors。古い順)、data.ranking(直近 30 日の PV の多い順に最大 10 件。article_id・title・path・views・unique_visitors)'),
            new OA\Response(response: 401, description: '未ログイン'),
        ]
    )]
    public function show(Request $request, PageViewStatsService $stats): JsonResponse
    {
        $stats = $stats->forUserArticles($request->user());
        $today = CarbonImmutable::today();
        $from = $today->subDays(self::DAYS - 1);

        return response()->json([
            'data' => [
                'summary' => $stats->summary(),
                'daily' => $stats->daily($from, $today),
                'ranking' => $stats->ranking($from, $today->endOfDay(), self::RANKING_LIMIT)->map(fn (array $row) => [
                    'article_id' => $row['content_id'],
                    'title' => $row['label'],
                    'path' => $row['path'],
                    'views' => $row['views'],
                    'unique_visitors' => $row['unique_visitors'],
                ]),
            ],
        ]);
    }
}
