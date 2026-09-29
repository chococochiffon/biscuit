<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReordersRows;
use App\Http\Requests\StoreGalleryImageRequest;
use App\Http\Requests\UpdateGalleryImageRequest;
use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GalleryImageController extends Controller
{
    use ReordersRows;

    /**
     * 分類の絞り込みで「未分類」を表す値。
     */
    public const UNCATEGORIZED = 'none';

    /**
     * デフォルトの並び順(更新日時の新しい順)。
     */
    private const DEFAULT_SORT = 'updated_at_desc';

    /**
     * ドラッグでの並び替えを有効にする並び順(表示順)。
     */
    private const REORDERABLE_SORT = 'sort_order';

    /**
     * ギャラリー画像の一覧を、分類(GET パラメータ category。分類の id、未分類は UNCATEGORIZED)で絞り込み、
     * 選択した並び順(デフォルトは更新日時の新しい順)で表示する。不正な値はリダイレクトせずに無視する。
     * ドラッグでの並び替え(表示順の保存)は、並び順が「表示順」かつ検索条件なしの場合のみ有効にする。
     */
    public function index(Request $request): View
    {
        $filters = Validator::make($request->query(), [
            'category' => ['nullable', 'regex:/^('.self::UNCATEGORIZED.'|[1-9][0-9]*)$/'],
            'sort' => ['nullable', Rule::in(array_keys($this->listSortOptions()))],
        ])->valid();

        $category = $filters['category'] ?? null;
        $sort = $filters['sort'] ?? self::DEFAULT_SORT;
        $isSearching = $category !== null;
        ['column' => $column, 'direction' => $direction] = $this->listSortOptions()[$sort];

        $galleryImages = GalleryImage::query()
            ->with('category')
            ->when($category === self::UNCATEGORIZED, fn ($query) => $query->whereNull('gallery_category_id'))
            ->when($isSearching && $category !== self::UNCATEGORIZED, fn ($query) => $query->where('gallery_category_id', $category))
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate(config('limits.admin_per_page'))
            ->withQueryString();

        $categories = GalleryCategory::query()->ordered()->get();
        $canReorder = $sort === self::REORDERABLE_SORT && ! $isSearching;

        return view('admin.gallery_images.index', compact('galleryImages', 'categories', 'category', 'sort', 'isSearching', 'canReorder'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $categories = GalleryCategory::query()->ordered()->get();

        return view('admin.gallery_images.create', compact('categories'));
    }

    /**
     * ギャラリー画像を登録する。並び順は末尾にする。
     */
    public function store(StoreGalleryImageRequest $request): RedirectResponse
    {
        AuditLogger::createWithLog(fn () => GalleryImage::create([
            ...$request->safe()->except('image'),
            'image' => GalleryImage::storeImage($request->file('image')),
            'sort_order' => GalleryImage::nextSortOrder(),
        ]));

        return redirect()->route('admin.gallery-images.index')->with('status', __('ギャラリー画像を登録しました。'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(GalleryImage $galleryImage): View
    {
        $categories = GalleryCategory::query()->ordered()->get();

        return view('admin.gallery_images.edit', compact('galleryImage', 'categories'));
    }

    /**
     * ギャラリー画像を更新する。画像が送信されなければ登録済みの画像のままにする。
     */
    public function update(UpdateGalleryImageRequest $request, GalleryImage $galleryImage): RedirectResponse
    {
        AuditLogger::updateWithLog($galleryImage, fn () => $galleryImage->update([
            ...$request->safe()->except('image'),
            ...($request->hasFile('image') ? ['image' => GalleryImage::storeImage($request->file('image'))] : []),
        ]));

        return redirect()->route('admin.gallery-images.index')->with('status', __('ギャラリー画像を更新しました。'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GalleryImage $galleryImage): RedirectResponse
    {
        AuditLogger::deleteWithLog($galleryImage);

        return redirect()->route('admin.gallery-images.index')->with('status', __('ギャラリー画像を削除しました。'));
    }

    /**
     * ドラッグ&ドロップで並び替えたギャラリー画像の並び順(sort_order)を保存する。
     * 表示中のページの画像を、ページ先頭の位置(offset)からの連番にする。
     */
    public function reorder(Request $request): RedirectResponse
    {
        $saved = $this->saveReorder($request, GalleryImage::class, paginated: true);

        return redirect()->route('admin.gallery-images.index', array_filter(['sort' => self::REORDERABLE_SORT, 'page' => $saved['page']]))
            ->with('status', __('並び替えを保存しました。'));
    }

    /**
     * 一覧で選択可能な並び順。名前・分類(分類の並び順。未分類は先頭)・更新日時と、表示順(ドラッグで並び替える順)。
     *
     * @return array<string, array{column: string|Builder, direction: string}>
     */
    private function listSortOptions(): array
    {
        $categorySortOrder = GalleryCategory::query()
            ->select('sort_order')
            ->whereColumn('gallery_categories.id', 'gallery_images.gallery_category_id');

        return [
            'updated_at_desc' => ['column' => 'updated_at', 'direction' => 'desc'],
            'updated_at_asc' => ['column' => 'updated_at', 'direction' => 'asc'],
            'name_asc' => ['column' => 'name', 'direction' => 'asc'],
            'name_desc' => ['column' => 'name', 'direction' => 'desc'],
            'category_asc' => ['column' => $categorySortOrder, 'direction' => 'asc'],
            'category_desc' => ['column' => $categorySortOrder, 'direction' => 'desc'],
            self::REORDERABLE_SORT => ['column' => 'sort_order', 'direction' => 'asc'],
        ];
    }
}
