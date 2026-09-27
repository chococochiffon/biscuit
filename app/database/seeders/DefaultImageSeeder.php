<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DefaultImageSeeder extends Seeder
{
    /**
     * 画像未設定時に表示するデフォルト画像の(publicディスク基準の)パス。
     * 同名のファイルを database/seeders/images/ に置いておき、この位置へコピーする。
     *
     * @var list<string>
     */
    private const DEFAULT_IMAGE_PATHS = [
        Article::DEFAULT_THUMBNAIL_PATH,
        SiteSetting::DEFAULT_SITE_ICON_PATH,
        SiteSetting::DEFAULT_SITE_IMAGE_PATH,
    ];

    /**
     * デフォルト画像を公開ディスクへ配置する。すでにファイルがある場合は上書きしない(差し替えた画像を残す)。
     */
    public function run(): void
    {
        $disk = Storage::disk('public');

        foreach (self::DEFAULT_IMAGE_PATHS as $path) {
            if (! $disk->exists($path)) {
                $disk->put($path, File::get(self::sourcePath(basename($path))));
            }
        }
    }

    /**
     * シーダー用に git 管理している画像ファイルの絶対パス。
     */
    public static function sourcePath(string $filename): string
    {
        return database_path('seeders/images/'.$filename);
    }
}
