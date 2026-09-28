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
     * - カスタムページの一覧(例: /recipes): type=custom_page_list と種類(custom_page_type)。一覧は GET /api/custom-pages/{name} で取得する
     * - カスタムページ(例: /recipes/nikujaga): type=custom_page と本文(data。詳細とカスタムフォームの項目を含む)、種類、本文内の呼び出しコンテンツ一覧
     * - それ以外: type=single_page/article と本文(data)、本文内(Inside)の呼び出しコンテンツ一覧
     *   (本文内の原文枠には本文が入り、データ種別が本文と異なる原文枠は含めない)
     * 本文は固定ページ、なければ公開済みの記事から探し、どちらも公開期間外のものは該当なしとして扱う。
     * URL の先頭がカスタムページの種類(カスタム名の複数形)なら、カスタムページとして扱う(記事・固定ページはこの先頭を使えない)。
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
            new OA\Response(response: 200, description: 'type(top/single_page/article/custom_page_list/custom_page)、data(本文。トップ・カスタムページの一覧はnull)、custom_page_type(カスタムページのみ)、call_contents(トップはトップ、それ以外は本文内の呼び出しコンテンツ。各要素は call_type・call_name・title・subtitle と table_name をキーにした実データ)'),
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

        $customPageType = CustomPageType::query()->get()->first(fn (CustomPageType $type) => str_starts_with($path.'/', $type->publicPath().'/'));

        if ($customPageType !== null) {
            return $this->resolveCustomPage($customPageType, $path);
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
            ->published()
            ->with('details')
            ->first();

        return $singlePage ?? Article::query()
            ->where('path', $path)
            ->published()
            ->with(['user', 'tags'])
            ->firstOrFail();
    }

    /**
     * カスタムページの一覧(/カスタム名の複数形)か、1 件(/カスタム名の複数形/スラッグ。スラッグ未入力の記事型は id)を返す。
     * 公開されていないページや、それより深い階層は 404。
     */
    private function resolveCustomPage(CustomPageType $type, string $path): JsonResponse
    {
        $rest = trim(substr($path, strlen($type->publicPath())), '/');

        if ($rest === '') {
            return response()->json([
                'type' => 'custom_page_list',
                'data' => null,
                'custom_page_type' => new CustomPageTypeResource($type),
                'call_contents' => [],
            ]);
        }

        abort_if(str_contains($rest, '/'), 404);

        $entry = CustomPageEntry::publishedQueryFor($type)
            ->where(fn ($query) => $query
                ->where('slug', $rest)
                ->when(! $type->hasDetails() && ctype_digit($rest), fn ($query) => $query->orWhere(fn ($query) => $query->whereNull('slug')->whereKey($rest))))
            ->firstOrFail();

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
