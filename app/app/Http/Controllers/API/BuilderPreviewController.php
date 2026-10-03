<?php

namespace App\Http\Controllers\API;

use App\Enums\BuilderPageType;
use App\Http\Controllers\Controller;
use App\Models\PageBuilder;
use App\Support\Builder\BuilderPresenter;
use App\Support\PublicPageResponder;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class BuilderPreviewController extends Controller
{
    /**
     * ページビルダーの編集中の内容を、公開側で表示したときと同じ形(パス解決 API と同じ)で返す(chococo のプレビュー用)。
     * 管理画面のエディタが発行した署名付きの URL(期限付き)でだけ開ける。固定ページは公開期間外・use_builder が false でも、
     * 編集中の内容を data.builder に入れて返す。表示する期間の外のブロックも返す。検索エンジンに載らないよう X-Robots-Tag: noindex を付ける。
     */
    #[OA\Get(
        path: '/builder-previews/{pageBuilder}',
        summary: 'ページビルダーの編集中の内容をプレビュー用に取得する(管理画面が発行した署名付き URL のみ)',
        tags: ['Resolve'],
        parameters: [
            new OA\Parameter(name: 'pageBuilder', in: 'path', required: true, description: 'ページビルダーの id', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'expires', in: 'query', required: true, description: '署名の有効期限(UNIX 時刻)', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'signature', in: 'query', required: true, description: '署名', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'パス解決 API と同じ形(type は top/single_page)。トップは builder、固定ページは data.builder に編集中の内容'),
            new OA\Response(response: 403, description: '署名が不正、または期限切れ'),
            new OA\Response(response: 404, description: 'ページビルダー、または対象の固定ページが削除されている'),
        ]
    )]
    public function show(PageBuilder $pageBuilder, PublicPageResponder $responder): JsonResponse
    {
        // 表示する期間の外のブロック(これから始まるキャンペーンなど)も、プレビューでは確かめられるよう取り除かずに返す
        $response = BuilderPresenter::showingAllPeriods(fn () => $pageBuilder->page_type === BuilderPageType::Top
            ? $responder->top($pageBuilder->draft_content)
            : $responder->pageContent($pageBuilder->singlePage ?? abort(404), $pageBuilder->draft_content));

        return $response->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
