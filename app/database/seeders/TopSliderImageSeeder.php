<?php

namespace Database\Seeders;

use App\Models\TopSliderImage;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

class TopSliderImageSeeder extends Seeder
{
    /**
     * database/seeders/images/ に置いたサンプル画像を、管理画面からのアップロードと同じく
     * 中央を16:9で切り抜いて 1920x1080 に縮小し、image/top_image にランダムなファイル名で保存して並び順どおりに登録する。
     * 登録済みのトップスライダー画像がある場合は何もしない(再実行で重複させない)。
     */
    public function run(): void
    {
        if (TopSliderImage::query()->exists()) {
            return;
        }

        $sampleImages = [
            'a_clean_warm_pastel_minimal_flat_soft_illustrat.png',
            'a_clean_warm_pastel_themed_promotional_collage.png',
            'wide_clean_pastel_cozy_promotional_illustration.png',
        ];

        foreach ($sampleImages as $sortOrder => $filename) {
            $file = new UploadedFile(DefaultImageSeeder::sourcePath($filename), $filename, null, null, true);

            TopSliderImage::create([
                'top_image' => TopSliderImage::storeImage($file),
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
