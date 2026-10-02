<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EditsBuilderContent;
use App\Http\Requests\SavePageBuilderComponentRequest;
use App\Models\GalleryCategory;
use App\Models\PageBuilderComponent;
use App\Models\PageBuilderTheme;
use App\Models\PageBuilderVersion;
use App\Support\Builder\BlockRegistry;
use App\Support\Builder\BuilderPresenter;
use App\Support\Builder\BuilderValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * グローバルコンポーネントの JSON。index はページのエディタのブロックの選択肢と Canvas の見本(公開中の内容)に使い、
 * show・update・publish・discard・versions・version はコンポーネントのエディタ(ページのエディタと同じ画面)が使う(下書き・公開の処理は EditsBuilderContent)。
 * コンポーネントの中にはグローバルコンポーネントのブロックを置けないため、エディタに渡すブロックの定義からも除く。
 */
class PageBuilderComponentJsonController extends Controller
{
    use EditsBuilderContent;

    /**
     * コンポーネント(グローバル・独自)の一覧(名前順)と、公開中の内容(エディタの形。未公開なら null)。ページのエディタの
     * グローバルコンポーネントのブロックの選択肢、パレットの独自コンポーネント、Canvas の見本と差し替えられる項目に使う。
     */
    public function index(): JsonResponse
    {
        return response()->json(PageBuilderComponent::query()->orderBy('name')->orderBy('id')->get()->map(fn (PageBuilderComponent $component) => [
            'id' => $component->id,
            'kind' => $component->kind->value,
            'name' => $component->name,
            'published' => $component->isPublished(),
            'content' => $component->isPublished() ? BuilderPresenter::forEditor($component->published_content) : null,
        ]));
    }

    /**
     * コンポーネントのエディタを開くときの内容。
     */
    public function show(PageBuilderComponent $pageBuilderComponent): JsonResponse
    {
        return response()->json([
            ...$this->state($pageBuilderComponent),
            'registry' => BlockRegistry::toArray($pageBuilderComponent->kind->context()),
            'image_base_url' => Storage::disk('public')->url(''),
            'gallery_categories' => GalleryCategory::query()->ordered()->get(['id', 'name']),
            'timezone' => config('app.timezone'),
            // テーマ(色のスタイルの theme:名前 と、ボタン・フォントの見本に使う)
            'theme' => PageBuilderTheme::current()->toPresentation(),
            'breadcrumbs' => [],
        ]);
    }

    /**
     * 下書きを保存する(自動保存も使う)。
     */
    public function update(SavePageBuilderComponentRequest $request, PageBuilderComponent $pageBuilderComponent): JsonResponse
    {
        if ($this->conflicts($pageBuilderComponent, $request->validated('updated_at'))) {
            return $this->conflictResponse($pageBuilderComponent);
        }

        $this->saveDraft($pageBuilderComponent, $request->content(), $pageBuilderComponent->name);

        return response()->json($this->state($pageBuilderComponent));
    }

    /**
     * 下書きを検証し直してから公開する(使っているページすべてに反映される)。
     */
    public function publish(Request $request, BuilderValidator $validator, PageBuilderComponent $pageBuilderComponent): JsonResponse
    {
        if ($this->conflicts($pageBuilderComponent, $request->input('updated_at'))) {
            return $this->conflictResponse($pageBuilderComponent);
        }

        return $this->publishDraft($pageBuilderComponent, $validator, $pageBuilderComponent->name, $pageBuilderComponent->kind->context())
            ?? response()->json($this->state($pageBuilderComponent));
    }

    /**
     * 下書きを公開中の内容に戻す(公開していなければ戻せない)。
     */
    public function discard(Request $request, PageBuilderComponent $pageBuilderComponent): JsonResponse
    {
        if (! $pageBuilderComponent->isPublished()) {
            return response()->json(['message' => __('公開中の内容がないため、元に戻せません。')], 422);
        }

        if ($this->conflicts($pageBuilderComponent, $request->input('updated_at'))) {
            return $this->conflictResponse($pageBuilderComponent);
        }

        $this->discardDraft($pageBuilderComponent, $pageBuilderComponent->name);

        return response()->json($this->state($pageBuilderComponent));
    }

    /**
     * 版の履歴(公開した内容の一覧。新しい順)。
     */
    public function versions(PageBuilderComponent $pageBuilderComponent): JsonResponse
    {
        return $this->versionList($pageBuilderComponent);
    }

    /**
     * 版の内容(エディタが下書きに読み込む)。
     */
    public function version(PageBuilderComponent $pageBuilderComponent, PageBuilderVersion $pageBuilderVersion): JsonResponse
    {
        return $this->versionContent($pageBuilderComponent, $pageBuilderVersion);
    }

    /**
     * エディタに返すコンポーネントの状態。
     *
     * @return array<string, mixed>
     */
    private function state(PageBuilderComponent $component): array
    {
        return [
            'page' => [
                'type' => 'component',
                // コンポーネントの種類(global・custom。独自コンポーネントのエディタでは差し替えられる項目を選べる)
                'kind' => $component->kind->value,
                'id' => $component->id,
                'title' => $component->name,
                'path' => null,
                'use_builder' => true,
            ],
            ...$this->contentState($component),
        ];
    }
}
