<?php

namespace App\Http\Controllers\API;

use App\Enums\ArticleApprovalStatus;
use App\Enums\CallContentPlace;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\CallContentResource;
use App\Http\Resources\SinglePageResource;
use App\Models\Article;
use App\Models\CallContent;
use App\Models\SinglePage;
use App\Support\CallContentResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use OpenApi\Attributes as OA;

class ResolveController extends Controller
{
    /**
     * 公開側のURLのパスから、そのページに表示する内容を取得する(フロントエンドのルーター用)。
     * - / (トップ): type=top と、トップ(Top)の呼び出しコンテンツ一覧
     * - それ以外: type=single_page/article と本文(data)、本文内(Inside)の呼び出しコンテンツ一覧
     *   (本文内の原文枠には本文が入り、データ種別が本文と異なる原文枠は含めない)
     * 本文は固定ページ、なければ公開済みの記事から探し、どちらも公開期間外のものは該当なしとして扱う。
     * 呼び出しコンテンツはいずれも並び順(sort_order)で返す。
     */
    #[OA\Get(
        path: '/resolve',
        summary: 'URLのパスからページの内容(本文と呼び出しコンテンツ)を取得する',
        tags: ['Resolve'],
        parameters: [
            new OA\Parameter(name: 'path', in: 'query', required: true, description: '公開側URLのパス(例: /、/company/about、/news/123)', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'type(top/single_page/article)、data(本文。トップはnull)、call_contents(トップはトップ、それ以外は本文内の呼び出しコンテンツ。各要素は call_type・call_name と table_name をキーにした実データ)'),
            new OA\Response(response: 404, description: 'パスに該当するコンテンツがない、公開期間外、または記事が未公開'),
            new OA\Response(response: 422, description: 'path が未指定'),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
        ]);

        $path = '/'.trim($validated['path'], '/');

        if ($path === '/') {
            return response()->json([
                'type' => 'top',
                'data' => null,
                'call_contents' => $this->callContents(CallContentPlace::Top),
            ]);
        }

        $pageContent = $this->findPageContent($path);

        return response()->json([
            'type' => $pageContent instanceof Article ? 'article' : 'single_page',
            'data' => $pageContent instanceof Article ? new ArticleResource($pageContent) : new SinglePageResource($pageContent),
            'call_contents' => $this->callContents(CallContentPlace::Inside, $pageContent),
        ]);
    }

    /**
     * パスに一致する公開期間内の固定ページ、なければ公開済みかつ公開期間内の記事を取得する(どちらもなければ404)。
     */
    private function findPageContent(string $path): Article|SinglePage
    {
        $singlePage = SinglePage::query()
            ->where('path', $path)
            ->withinPublicationPeriod()
            ->with('details')
            ->first();

        return $singlePage ?? Article::query()
            ->where('path', $path)
            ->where('approval', ArticleApprovalStatus::Published)
            ->withinPublicationPeriod()
            ->with(['user', 'tags'])
            ->firstOrFail();
    }

    /**
     * 設置場所の呼び出しコンテンツを並び順で整形する。ページの本文を渡した場合は、そのページに表示しない枠を除く。
     *
     * @return Collection<int, CallContentResource>
     */
    private function callContents(CallContentPlace $place, ?Model $pageContent = null): Collection
    {
        $resolver = new CallContentResolver;

        return CallContent::query()
            ->with('contentModelRelation')
            ->forPlace($place)
            ->get()
            ->filter(fn (CallContent $callContent) => $pageContent === null || $resolver->appliesToPage($callContent, $pageContent))
            ->values()
            ->map(fn (CallContent $callContent) => (new CallContentResource($callContent))->withPageContent($pageContent));
    }
}
