<?php

namespace App\Support\CallContent;

use App\Models\GalleryImage;
use Illuminate\Database\Eloquent\Collection;

/**
 * 呼び出しコンテンツ(CallContent)がGalleryImageを参照する場合の実データ取得を担う。
 */
class GalleryImageContentSource
{
    /**
     * タイルリスト表示用に、公開中のギャラリー画像を並び順(sort_order、同順なら id)で指定件数取得する。
     *
     * @return Collection<int, GalleryImage>
     */
    public function getTileList(int $count): Collection
    {
        return GalleryImage::query()->published()->with('category')->ordered()->take($count)->get();
    }
}
