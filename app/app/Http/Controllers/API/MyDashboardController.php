<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\MyDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class MyDashboardController extends Controller
{
    /**
     * マイページのダッシュボード用に、ログイン中のユーザー自身の記事・ギャラリーの画像の状況・最近の操作・アカウント・画像をまとめて返す。
     * 管理画面のダッシュボードの項目をユーザー本人の分に絞ったもので、サイト全体やほかのユーザーの数字は返さない(アクセスは GET /me/page-views)。
     */
    #[OA\Get(
        path: '/me/dashboard',
        summary: '自分のダッシュボード(記事・ギャラリーの状況など)を取得する',
        security: [['bearer' => []]],
        tags: ['Me'],
        responses: [
            new OA\Response(response: 200, description: 'data.counts(articles: total・published・scheduled・draft・pending・unpublished、gallery_images: total・published・draft・pending)、data.recent_contents(最近編集した記事・画像。type(article・gallery_image)・id・title・status・updated_at)、data.scheduled(today・this_week の予約公開の記事。id・title・publish_at)、data.warnings(key(returned・broken_links・no_thumbnail・pending)・count・items。broken_links の items は切れているリンク links も持つ)、data.recent_activities(自分の最近の操作。ログイン・ログアウト・確認コードの送信は除く。action・action_label・subject_type・subject_type_label・subject_label・created_at)、data.account(skip_approval・public_profile・profile_path・recent_logins)、data.media(count・bytes・groups(thumbnail・gallery・icon ごとの count・bytes))'),
            new OA\Response(response: 401, description: '未ログイン'),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $dashboard = app(MyDashboardService::class, ['user' => $request->user()]);

        return response()->json([
            'data' => [
                'counts' => $dashboard->contentCounts(),
                'recent_contents' => $dashboard->recentContents(),
                'scheduled' => $dashboard->scheduledContents(),
                'warnings' => $dashboard->contentWarnings(),
                'recent_activities' => $dashboard->recentActivities(),
                'account' => $dashboard->account(),
                'media' => $dashboard->media(),
            ],
        ]);
    }
}
