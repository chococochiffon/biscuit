<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGalleryImageRequest;
use App\Http\Requests\UpdateGalleryImageRequest;
use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GalleryImageController extends Controller
{
    /**
     * ギャラリー画像の一覧を並び順(公開側の表示順)で表示する。行をドラッグして並び替えられる。
     */
    public function index(): View
    {
        $galleryImages = GalleryImage::query()
            ->with('category')
            ->ordered()
            ->paginate(config('limits.admin_per_page'));

        return view('admin.gallery_images.index', compact('galleryImages'));
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
        GalleryImage::create([
            ...$request->safe()->except('image'),
            'image' => GalleryImage::storeImage($request->file('image')),
            'sort_order' => (GalleryImage::max('sort_order') ?? -1) + 1,
        ]);

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
        $galleryImage->update([
            ...$request->safe()->except('image'),
            ...($request->hasFile('image') ? ['image' => GalleryImage::storeImage($request->file('image'))] : []),
        ]);

        return redirect()->route('admin.gallery-images.index')->with('status', __('ギャラリー画像を更新しました。'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GalleryImage $galleryImage): RedirectResponse
    {
        $galleryImage->delete();

        return redirect()->route('admin.gallery-images.index')->with('status', __('ギャラリー画像を削除しました。'));
    }

    /**
     * ドラッグ&ドロップで並び替えたギャラリー画像の並び順(sort_order)を保存する。
     * 表示中のページの画像を、ページ先頭の位置(offset)からの連番にする。
     */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', Rule::exists('gallery_images', 'id')],
            'offset' => ['nullable', 'integer', 'min:0'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $offset = (int) ($validated['offset'] ?? 0);

        // 途中で失敗しても並び順が中途半端にならないよう、まとめて保存する
        DB::transaction(function () use ($validated, $offset) {
            foreach (array_values($validated['order']) as $index => $id) {
                GalleryImage::query()->whereKey($id)->update(['sort_order' => $offset + $index]);
            }
        });

        return redirect()->route('admin.gallery-images.index', array_filter(['page' => $validated['page'] ?? null]))
            ->with('status', __('並び替えを保存しました。'));
    }
}
