<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SyncsSortableRows;
use App\Http\Requests\UpdateGalleryCategoriesRequest;
use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GalleryCategoryController extends Controller
{
    use SyncsSortableRows;

    /**
     * ギャラリー画像の分類をまとめて編集する画面(行の追加・削除・ドラッグでの並び替え)を表示する。
     */
    public function edit(): View
    {
        $categories = GalleryCategory::query()->ordered()->get();

        return view('admin.gallery_categories.edit', compact('categories'));
    }

    /**
     * 送信された分類の行にデータベースを同期する(作成/更新/削除と並び順の扱いは SyncsSortableRows::syncSortableRows() を参照)。
     * 削除した分類に属していた画像は未分類にする。
     */
    public function update(UpdateGalleryCategoriesRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $submittedIds = collect($request->validated('categories', []))->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

            GalleryImage::query()
                ->whereNotNull('gallery_category_id')
                ->whereNotIn('gallery_category_id', $submittedIds)
                ->update(['gallery_category_id' => null]);

            $this->syncSortableRows(GalleryCategory::query(), $request->validated('categories', []), fn (array $row) => [
                'name' => $row['name'],
            ]);
        });

        return redirect()->route('admin.gallery-categories.edit')->with('status', __('分類を保存しました。'));
    }
}
