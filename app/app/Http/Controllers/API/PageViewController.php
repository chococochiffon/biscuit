<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\RecordPageViewRequest;
use App\Services\PageViewService;
use App\Support\PublicPageResolver;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PageViewController extends Controller
{
    /**
     * 公開側のページが表示されたことを 1 PV として記録する(chococo のサーバーが中継する)。
     * パスはパス解決 API と同じく解決し、公開中のページがなければ(404 のページ)記録しない。
     * マイページにログイン中なら Authorization: Bearer でトークンを送ると、ユーザーを記録する。
     */
    #[OA\Post(
        path: '/page-views',
        summary: 'PV を記録する(chococo のサーバーから、共有の鍵 X-Page-View-Key を付けて呼ぶ)',
        tags: ['PageView'],
        parameters: [
            new OA\Parameter(name: 'X-Page-View-Key', in: 'header', required: true, description: 'chococo のサーバーとの共有の鍵(PAGE_VIEW_FORWARD_KEY)', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['path'], properties: [
            new OA\Property(property: 'path', type: 'string', description: '表示した公開側 URL のパス(例: /news/first-post)'),
            new OA\Property(property: 'visitor_id', type: 'string', format: 'uuid', nullable: true, description: '訪問者の識別子(初回は省略し、応答の visitor_id を保存して次から送る)'),
            new OA\Property(property: 'session_id', type: 'string', nullable: true, description: 'セッションの識別子(ハッシュにして保存する)'),
            new OA\Property(property: 'ip', type: 'string', nullable: true, description: '閲覧者の IP アドレス(ハッシュにして保存する)'),
            new OA\Property(property: 'user_agent', type: 'string', nullable: true, description: '閲覧者の User-Agent'),
            new OA\Property(property: 'referer', type: 'string', nullable: true, description: '閲覧者のブラウザが送った Referer'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'visitor_id(訪問者の識別子。送られた値が UUID でなければ新しく発行する)'),
            new OA\Response(response: 403, description: '共有の鍵が一致しない、または未設定'),
            new OA\Response(response: 404, description: 'パスに該当する公開中のページがない(記録しない)'),
            new OA\Response(response: 422, description: 'path が未指定、または IP アドレスの書式が不正'),
            new OA\Response(response: 429, description: '閲覧者ごとの回数が多すぎる'),
        ]
    )]
    public function store(RecordPageViewRequest $request, PublicPageResolver $resolver, PageViewService $pageViews): JsonResponse
    {
        $page = $resolver->resolve($request->validated('path')) ?? abort(404);

        return response()->json([
            'visitor_id' => $pageViews->record($request, $page),
        ], 201);
    }
}
