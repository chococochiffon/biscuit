<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersPublishableList;
use App\Http\Controllers\Concerns\SyncsSortableRows;
use App\Http\Requests\StoreSinglePageRequest;
use App\Http\Requests\UpdateSinglePageRequest;
use App\Models\SinglePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SinglePageController extends Controller
{
    use FiltersPublishableList, SyncsSortableRows;

    /**
     * ドラッグでの並び替えを有効にする並び順(表示順)。
     */
    private const REORDERABLE_SORT = 'sort_order';

    /**
     * Display a listing of the resource.
     * タイトル(部分一致)・公開開始/公開終了(日付の範囲)で検索し、選択した並び順(デフォルトは更新日時の新しい順)で表示する。
     * ドラッグでの並び替え(表示順の保存)は、並び順が「表示順」かつ検索条件なしの場合のみ有効にする。
     */
    public function index(Request $request): View
    {
        [$filters, $sort, $isSearching] = $this->listFilters($request);

        $singlePages = $this->applyListFilters(SinglePage::query(), $filters, $sort)
            ->paginate(config('limits.admin_per_page'))
            ->withQueryString();

        $canReorder = $sort === self::REORDERABLE_SORT && ! $isSearching;

        return view('admin.single_pages.index', compact('singlePages', 'filters', 'sort', 'isSearching', 'canReorder'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.single_pages.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSinglePageRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $singlePage = SinglePage::create([
                'title' => $request->validated('title'),
                'short_sentences' => $request->validated('short_sentences'),
                'parent_path' => $request->validated('parent_path'),
                'slug' => $request->validated('slug'),
                'top_page_view' => $request->boolean('top_page_view'),
                'link_list_view' => $request->boolean('link_list_view'),
                'sort_order' => (SinglePage::max('sort_order') ?? -1) + 1,
                'publication_start_datetime' => $request->validated('publication_start_datetime'),
                'publication_end_datetime' => $request->validated('publication_end_datetime'),
            ]);

            if ($request->hasFile('header_image')) {
                $singlePage->update(['header_image' => $singlePage->storeHeaderImage($request->file('header_image'))]);
            }

            $this->syncDetails($singlePage, $request->validated('details', []));
        });

        return redirect()->route('admin.single-pages.index')->with('status', __('固定ページを登録しました。'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SinglePage $singlePage): View
    {
        $singlePage->load('details');

        return view('admin.single_pages.edit', compact('singlePage'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSinglePageRequest $request, SinglePage $singlePage): RedirectResponse
    {
        DB::transaction(function () use ($request, $singlePage) {
            $singlePage->fill([
                'title' => $request->validated('title'),
                'short_sentences' => $request->validated('short_sentences'),
                'parent_path' => $request->validated('parent_path'),
                'slug' => $request->validated('slug'),
                'top_page_view' => $request->boolean('top_page_view'),
                'link_list_view' => $request->boolean('link_list_view'),
                'publication_start_datetime' => $request->validated('publication_start_datetime'),
                'publication_end_datetime' => $request->validated('publication_end_datetime'),
            ]);

            if ($request->hasFile('header_image')) {
                $singlePage->header_image = $singlePage->storeHeaderImage($request->file('header_image'));
            }

            $singlePage->save();

            $this->syncDetails($singlePage, $request->validated('details', []));
        });

        return redirect()->route('admin.single-pages.index')->with('status', __('固定ページを更新しました。'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SinglePage $singlePage): RedirectResponse
    {
        $singlePage->delete();

        return redirect()->route('admin.single-pages.index')->with('status', __('固定ページを削除しました。'));
    }

    /**
     * ドラッグ&ドロップで並び替えた固定ページの表示順(sort_order)を保存する。
     * 一覧はページネーションされているため、送信されたidの並びに現在のページの開始位置(offset)を加えて連番を振る。
     */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', Rule::exists('single_pages', 'id')],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $offset = (int) ($validated['offset'] ?? 0);

        // 途中で失敗しても並び順が中途半端にならないよう、まとめて保存する
        DB::transaction(function () use ($validated, $offset) {
            foreach (array_values($validated['order']) as $index => $id) {
                SinglePage::query()->whereKey($id)->update(['sort_order' => $offset + $index]);
            }
        });

        return redirect()->route('admin.single-pages.index', ['sort' => self::REORDERABLE_SORT])->with('status', __('並び替えを保存しました。'));
    }

    /**
     * フォームから送信された詳細(single_page_details)の内容にデータベースを同期する。
     * (作成/更新/削除と並び順の扱いは SyncsSortableRows::syncSortableRows() を参照)
     *
     * @param  array<int, array{id?: int|string|null, sub_title: string, contents?: string|null, sort_order?: int|string|null}>  $rows
     */
    private function syncDetails(SinglePage $singlePage, array $rows): void
    {
        $this->syncSortableRows($singlePage->details(), $rows, fn (array $row) => [
            'sub_title' => $row['sub_title'],
            'contents' => $row['contents'] ?? '',
        ]);
    }

    /**
     * 一覧で選択可能な並び順。共通の並び順に、表示順(ドラッグでの並び替え用)を足す。
     *
     * @return array<string, array{column: string, direction: string}>
     */
    protected function listSortOptions(): array
    {
        return [
            ...$this->commonListSortOptions(),
            self::REORDERABLE_SORT => ['column' => 'sort_order', 'direction' => 'asc'],
        ];
    }
}
