<?php

namespace Database\Seeders;

use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GallerySeeder extends Seeder
{
    /**
     * サンプルのギャラリーの分類と画像を登録する。
     * 画像は database/seeders/images/ のサンプル画像を、管理画面からのアップロードと同じく長辺 1200px 以内に縮小して
     * image/gallery にランダムなファイル名で保存する。登録済みのギャラリー画像がある場合は登録しない(再実行で重複させない)。
     * 登録の前に、どのレコードからも参照されていない画像ファイルを削除する(migrate:refresh --seed のたびに古い画像がたまらないようにする)。
     */
    public function run(): void
    {
        $this->deleteUnreferencedImages();

        if (GalleryImage::query()->exists()) {
            return;
        }

        $categories = collect(['イラスト', 'バナー'])
            ->mapWithKeys(fn (string $name, int $sortOrder) => [
                $name => GalleryCategory::query()->firstOrCreate(['name' => $name], ['sort_order' => $sortOrder]),
            ]);

        $sampleImages = [
            ['a_clean_warm_pastel_minimal_flat_soft_illustrat.png', 'やさしい色のイラスト', 'パステルカラーでまとめたシンプルなイラストです。', 'イラスト'],
            ['a_clean_warm_pastel_themed_promotional_collage.png', 'コラージュ', 'いろいろなモチーフを組み合わせたコラージュです。', 'イラスト'],
            ['wide_clean_pastel_cozy_promotional_illustration.png', 'くつろぎの風景', null, 'イラスト'],
            ['biscuit-og-image-1200x630.png', 'biscuit のバナー', 'SNS でシェアしたときに表示される画像です。', 'バナー'],
        ];

        foreach ($sampleImages as $sortOrder => [$filename, $name, $comment, $categoryName]) {
            $file = new UploadedFile(DefaultImageSeeder::sourcePath($filename), $filename, null, null, true);

            GalleryImage::create([
                'gallery_category_id' => $categories[$categoryName]->id,
                'image' => GalleryImage::storeImage($file),
                'name' => $name,
                'comment' => $comment,
                'sort_order' => $sortOrder,
            ]);
        }
    }

    /**
     * 保存先ディレクトリの画像のうち、ギャラリー画像のレコード(論理削除済みを含む)から参照されていないものを削除する。
     */
    private function deleteUnreferencedImages(): void
    {
        $disk = Storage::disk('public');
        $referencedPaths = GalleryImage::withTrashed()->pluck('image')->all();

        $disk->delete(array_values(array_diff($disk->files(GalleryImage::IMAGE_DIRECTORY), $referencedPaths)));
    }
}
