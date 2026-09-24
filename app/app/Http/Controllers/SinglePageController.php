<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSinglePageRequest;
use App\Http\Requests\UpdateSinglePageRequest;
use App\Models\SinglePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SinglePageController extends Controller
{
    /**
     * 一覧のデフォルトの並び順(更新日時の新しい順)。
     */
    private const DEFAULT_SORT = 'updated_at_desc';

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
        $sortOptions = $this->sortOptions();

        // 不正な検索条件でリダイレクトを繰り返さないよう、妥当な値だけを採用して残りは無視する
        $filters = Validator::make($request->query(), [
            'title' => ['nullable', 'string', 'max:255'],
            'publication_start_from' => ['nullable', 'date_format:Y-m-d'],
            'publication_start_to' => ['nullable', 'date_format:Y-m-d'],
            'publication_end_from' => ['nullable', 'date_format:Y-m-d'],
            'publication_end_to' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', Rule::in(array_keys($sortOptions))],
        ])->valid();

        $sort = $filters['sort'] ?? self::DEFAULT_SORT;
        ['column' => $column, 'direction' => $direction] = $sortOptions[$sort];

        $singlePages = SinglePage::query()
            ->when(filled($filters['title'] ?? null), fn ($query) => $query->where('title', 'like', '%'.$filters['title'].'%'))
            ->filterPublicationPeriod($filters)
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate(20)
            ->withQueryString();

        $isSearching = collect($filters)->except('sort')->filter(fn ($value) => filled($value))->isNotEmpty();
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

        return redirect()->route('admin.single-pages.index')->with('status', '固定ページを登録しました。');
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

        return redirect()->route('admin.single-pages.index')->with('status', '固定ページを更新しました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SinglePage $singlePage): RedirectResponse
    {
        $singlePage->delete();

        return redirect()->route('admin.single-pages.index')->with('status', '固定ページを削除しました。');
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

        foreach (array_values($validated['order']) as $index => $id) {
            SinglePage::query()->whereKey($id)->update(['sort_order' => $offset + $index]);
        }

        return redirect()->route('admin.single-pages.index', ['sort' => self::REORDERABLE_SORT])->with('status', '並び替えを保存しました。');
    }

    /**
     * フォームから送信された詳細(single_page_details)の内容にデータベースを同期する。
     * 送信された行はid有無で作成/更新し、送信されなかった既存行は削除する。
     *
     * @param  array<int, array{id?: int|string|null, sub_title: string, contents?: string|null, sort_order?: int|string|null}>  $rows
     */
    private function syncDetails(SinglePage $singlePage, array $rows): void
    {
        $submittedIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        $singlePage->details()->whereNotIn('id', $submittedIds)->delete();

        foreach ($rows as $index => $row) {
            $attributes = [
                'sub_title' => $row['sub_title'],
                'contents' => $row['contents'] ?? '',
                'sort_order' => $row['sort_order'] ?? $index,
            ];

            if (! empty($row['id'])) {
                $singlePage->details()->whereKey($row['id'])->update($attributes);
            } else {
                $singlePage->details()->create($attributes);
            }
        }
    }

    /**
     * 一覧で選択可能な並び順(キー → 並び替えるカラム・方向)。一覧の見出しクリックで「項目_asc/desc」のキーが送られる。
     *
     * @return array<string, array{column: string, direction: string}>
     */
    private function sortOptions(): array
    {
        return [
            'updated_at_desc' => ['column' => 'updated_at', 'direction' => 'desc'],
            'updated_at_asc' => ['column' => 'updated_at', 'direction' => 'asc'],
            'title_asc' => ['column' => 'title', 'direction' => 'asc'],
            'title_desc' => ['column' => 'title', 'direction' => 'desc'],
            'publication_start_desc' => ['column' => 'publication_start_datetime', 'direction' => 'desc'],
            'publication_start_asc' => ['column' => 'publication_start_datetime', 'direction' => 'asc'],
            'publication_end_desc' => ['column' => 'publication_end_datetime', 'direction' => 'desc'],
            'publication_end_asc' => ['column' => 'publication_end_datetime', 'direction' => 'asc'],
            self::REORDERABLE_SORT => ['column' => 'sort_order', 'direction' => 'asc'],
        ];
    }
}
