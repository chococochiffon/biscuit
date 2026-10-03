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
     * サンプルのギャラリーの分類と画像を登録する(開発用の DatabaseSeeder と、インストール時の InstallSeeder で使う)。
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

        $categories = collect(['昼のワークスペース', '夕方・夜のワークスペース'])
            ->mapWithKeys(fn (string $name, int $sortOrder) => [
                $name => GalleryCategory::query()->firstOrCreate(['name' => $name], ['sort_order' => $sortOrder]),
            ]);

        // chococo の見本のギャラリー(public/image/gallery/)と同じ画像
        $sampleImages = [
            ['gallery_001.png', '海の見える作業机', '朝の光が入る、窓辺のワークスペースです。', '昼のワークスペース'],
            ['gallery_002.png', '夜景と作業机', '街の明かりを眺めながら作業する夜のデスクです。', '夕方・夜のワークスペース'],
            ['gallery_003.png', '白いワークスペース', '高層ビルを望む、明るい白の作業机です。', '昼のワークスペース'],
            ['gallery_004.png', '夕焼けの作業机', '夕日の沈む海を眺めるデスクです。', '夕方・夜のワークスペース'],
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
