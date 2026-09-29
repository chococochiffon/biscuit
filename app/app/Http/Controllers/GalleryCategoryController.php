<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReordersRows;
use App\Http\Requests\StoreGalleryCategoryRequest;
use App\Http\Requests\UpdateGalleryCategoryRequest;
use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ギャラリー画像の分類を、分類管理モーダルから Ajax(JSON)で管理する。
 */
class GalleryCategoryController extends Controller
{
    use ReordersRows;

    /**
     * 分類の一覧を並び順(sort_order、同順なら id)で返す。
     */
    public function index(): JsonResponse
    {
        return response()->json(GalleryCategory::query()->ordered()->get(['id', 'name', 'sort_order']));
    }

    /**
     * 分類を登録する。並び順は末尾にする。
     */
    public function store(StoreGalleryCategoryRequest $request): JsonResponse
    {
        $galleryCategory = AuditLogger::createWithLog(fn () => GalleryCategory::create([
            ...$request->validated(),
            'sort_order' => (GalleryCategory::max('sort_order') ?? -1) + 1,
        ]));

        return response()->json($galleryCategory, 201);
    }

    /**
     * 分類の名前を変更する。
     */
    public function update(UpdateGalleryCategoryRequest $request, GalleryCategory $galleryCategory): JsonResponse
    {
        AuditLogger::updateWithLog($galleryCategory, fn () => $galleryCategory->update($request->validated()));

        return response()->json($galleryCategory);
    }

    /**
     * 分類を削除(論理削除)し、その分類の画像を未分類にする。
     */
    public function destroy(GalleryCategory $galleryCategory): JsonResponse
    {
        DB::transaction(function () use ($galleryCategory) {
            $uncategorized = GalleryImage::query()->where('gallery_category_id', $galleryCategory->id)->update(['gallery_category_id' => null]);
            $galleryCategory->delete();
            AuditLogger::deleted($galleryCategory, ['uncategorized_images' => $uncategorized]);
        });

        return response()->json(status: 204);
    }

    /**
     * ドラッグ&ドロップで並び替えた分類の並び順(sort_order)を、送信された順の連番で保存する。
     */
    public function reorder(Request $request): JsonResponse
    {
        $this->saveReorder($request, GalleryCategory::class);

        return response()->json(status: 204);
    }
}
