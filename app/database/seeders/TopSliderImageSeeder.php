<?php

namespace Database\Seeders;

use App\Models\TopSliderImage;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TopSliderImageSeeder extends Seeder
{
    /**
     * 公開ディスクの image/ に置いたサンプル画像を、管理画面からのアップロードと同じく
     * 中央を16:9で切り抜いて 1920x1080 に縮小し、image/top_image にランダムなファイル名で保存して並び順どおりに登録する。
     * 登録済みのトップスライダー画像がある場合は何もしない(再実行で重複させない)。
     */
    public function run(): void
    {
        if (TopSliderImage::query()->exists()) {
            return;
        }

        $sampleImages = [
            'image/a_clean_warm_pastel_minimal_flat_soft_illustrat.png',
            'image/a_clean_warm_pastel_themed_promotional_collage.png',
            'image/wide_clean_pastel_cozy_promotional_illustration.png',
        ];

        foreach ($sampleImages as $sortOrder => $path) {
            $file = new UploadedFile(Storage::disk('public')->path($path), basename($path), null, null, true);

            TopSliderImage::create([
                'top_image' => TopSliderImage::storeImage($file),
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
