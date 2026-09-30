<?php

namespace App\Http\Controllers\API;

use App\Enums\CallContentPlace;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\CallContentResource;
use App\Http\Resources\CustomPageEntryResource;
use App\Http\Resources\CustomPageTypeResource;
use App\Http\Resources\SinglePageResource;
use App\Models\Article;
use App\Models\CallContent;
use App\Models\CustomPages\CustomForm;
use App\Models\CustomPages\CustomFormValue;
use App\Models\CustomPages\CustomPageDetail;
use App\Models\CustomPages\CustomPageEntry;
use App\Models\CustomPageType;
use App\Models\SinglePage;
use App\Support\Breadcrumbs;
use App\Support\CallContentResolver;
use App\Support\PublicPage;
use App\Support\PublicPageResolver;
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
     * - カスタムページの一覧(例: /recipes): type=custom_page_list と種類(custom_page_type)。一覧は GET /api/custom-pages/{name} で取得する
     * - カスタムページ(例: /recipes/nikujaga): type=custom_page と本文(data。詳細とカスタムフォームの項目を含む)、種類、本文内の呼び出しコンテンツ一覧
     * - それ以外: type=single_page/article と本文(data)、本文内(Inside)の呼び出しコンテンツ一覧
     *   (本文内の原文枠には本文が入り、データ種別が本文と異なる原文枠は含めない)
     * パスの解決は Support\PublicPageResolver(PV の記録と共通)。本文は固定ページ、なければ公開済みの記事から探し、どちらも公開期間外のものは該当なしとして扱う。
     * URL の先頭がカスタムページの種類(カスタム名の複数形)なら、カスタムページとして扱う(記事・固定ページはこの先頭を使えない)。
     * 呼び出しコンテンツはいずれも並び順(sort_order)で返す。
     * breadcrumbs はパンくず(Home から表示中のページまで。各項目は label と path(リンクしない途中の階層は null)。トップは空。Support\Breadcrumbs)。
     */
    #[OA\Get(
        path: '/resolve',
        summary: 'URLのパスからページの内容(本文と呼び出しコンテンツ)を取得する',
        tags: ['Resolve'],
        parameters: [
            new OA\Parameter(name: 'path', in: 'query', required: true, description: '公開側URLのパス(例: /、/company/about、/news/123)', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'type(top/single_page/article/custom_page_list/custom_page)、data(本文。トップ・カスタムページの一覧はnull)、custom_page_type(カスタムページのみ)、breadcrumbs(パンくず。各項目は label・path(リンクしない途中の階層は null)。トップは空)、call_contents(トップはトップ、それ以外は本文内の呼び出しコンテンツ。各要素は call_type・call_name・title・subtitle と table_name をキーにした実データ)'),
            new OA\Response(response: 404, description: 'パスに該当するコンテンツがない、公開期間外、または記事が未公開'),
            new OA\Response(response: 422, description: 'path が未指定'),
        ]
    )]
    public function __invoke(Request $request, PublicPageResolver $resolver): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
        ]);

        $page = $resolver->resolve($validated['path']) ?? abort(404);

        return match ($page->type) {
            PublicPage::TYPE_TOP => response()->json([
                'type' => 'top',
                'data' => null,
                'breadcrumbs' => [],
                'call_contents' => $this->callContents(CallContentPlace::Top),
            ]),
            PublicPage::TYPE_CUSTOM_PAGE_LIST => response()->json([
                'type' => 'custom_page_list',
                'data' => null,
                'custom_page_type' => new CustomPageTypeResource($page->customPageType),
                'breadcrumbs' => Breadcrumbs::forCustomPageList($page->customPageType),
                'call_contents' => [],
            ]),
            PublicPage::TYPE_CUSTOM_PAGE => $this->customPageResponse($page->customPageType, $page->content),
            default => $this->pageContentResponse($page->content),
        };
    }

    /**
     * 固定ページ・記事の本文を返す。
     */
    private function pageContentResponse(Article|SinglePage $pageContent): JsonResponse
    {
        $pageContent->load($pageContent instanceof Article ? ['user.detail', 'tags'] : ['details']);

        return response()->json([
            'type' => $pageContent instanceof Article ? 'article' : 'single_page',
            'data' => $pageContent instanceof Article ? new ArticleResource($pageContent) : new SinglePageResource($pageContent),
            'breadcrumbs' => Breadcrumbs::forPage($pageContent->path, $pageContent->title),
            'call_contents' => $this->callContents(CallContentPlace::Inside, $pageContent),
        ]);
    }

    /**
     * カスタムページ 1 件を、詳細とカスタムフォームの項目付きで返す。
     */
    private function customPageResponse(CustomPageType $type, CustomPageEntry $entry): JsonResponse
    {
        $resource = (new CustomPageEntryResource($entry))->withCustomFields(
            CustomForm::queryFor($type)->ordered()->get(),
            CustomFormValue::queryFor($type)->where($type->entryForeignKey(), $entry->id)->pluck('value', $type->formForeignKey()),
        );

        if ($type->hasDetails()) {
            $resource->withDetails(CustomPageDetail::queryFor($type)->where($type->entryForeignKey(), $entry->id)->ordered()->get());
        }

        return response()->json([
            'type' => 'custom_page',
            'data' => $resource,
            'custom_page_type' => new CustomPageTypeResource($type),
            'breadcrumbs' => Breadcrumbs::forCustomPage($type, $entry),
            'call_contents' => $this->callContents(CallContentPlace::Inside, $entry),
        ]);
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
