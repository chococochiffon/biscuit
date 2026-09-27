<?php

namespace Database\Seeders;

use App\Models\TopSliderImage;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TopSliderImageSeeder extends Seeder
{
    /**
     * database/seeders/images/ に置いたサンプル画像を、管理画面からのアップロードと同じく
     * 中央を16:9で切り抜いて 1920x1080 に縮小し、image/top_image にランダムなファイル名で保存して並び順どおりに登録する。
     * 登録済みのトップスライダー画像がある場合は登録しない(再実行で重複させない)。
     * 登録の前に、どのレコードからも参照されていない画像ファイルを削除する(migrate:refresh --seed のたびに古い画像がたまらないようにする)。
     */
    public function run(): void
    {
        $this->deleteUnreferencedImages();

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

    /**
     * 保存先ディレクトリの画像のうち、トップスライダー画像のレコード(論理削除済みを含む)から参照されていないものを削除する。
     */
    private function deleteUnreferencedImages(): void
    {
        $disk = Storage::disk('public');
        $referencedPaths = TopSliderImage::withTrashed()->pluck('top_image')->all();

        $disk->delete(array_values(array_diff($disk->files(TopSliderImage::IMAGE_DIRECTORY), $referencedPaths)));
    }
}
