<?php

namespace App\Services;

use App\Models\Article;
use App\Models\CustomPageType;
use App\Models\GalleryImage;
use App\Models\PageBuilder;
use App\Models\SinglePage;
use App\Models\SiteSetting;
use App\Models\TopSliderImage;
use App\Models\UserDetail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * 管理画面のダッシュボードに出す、アップロードした画像(public ディスクの image/ 配下)の件数・容量と未使用の画像をまとめる。
 * 未使用は、どのレコード(論理削除済みを含む。復元できるため)からも参照されていない画像。
 * image/ の直下の画像(デフォルト画像など)は数えない。ディスクとテーブル全体を走査するため、結果を CACHE_SECONDS 秒キャッシュする。
 */
class MediaStatsService
{
    public const CACHE_KEY = 'dashboard.media_stats';

    public const CACHE_SECONDS = 300;

    /**
     * 未使用の画像として並べる件数。
     */
    public const UNUSED_ITEM_LIMIT = 5;

    /**
     * 画像や本文(画像の URL を含む HTML)を持つテーブル。カスタムページの種類ごとのテーブルは別に足す。
     */
    private const REFERENCING_TABLES = [
        'articles',
        'single_pages',
        'single_page_details',
        'site_settings',
        'top_slider_images',
        'user_details',
        'gallery_images',
        'layout_blocks',
        'answers',
        'questions',
        'question_answers',
        'page_builders',
    ];

    /**
     * 画像の保存先ごとの件数・容量と、未使用の画像の件数・容量・一覧(新しい順に UNUSED_ITEM_LIMIT 件)。
     *
     * @return array{directories: list<array{label: string, directory: string, count: int, bytes: int}>, total_count: int, total_bytes: int, unused_count: int, unused_bytes: int, unused_items: list<string>, calculated_at: string}
     */
    public function stats(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => $this->calculate());
    }

    /**
     * @return array{directories: list<array{label: string, directory: string, count: int, bytes: int}>, total_count: int, total_bytes: int, unused_count: int, unused_bytes: int, unused_items: list<string>, calculated_at: string}
     */
    private function calculate(): array
    {
        $disk = Storage::disk('public');
        $referenced = $this->referencedPaths();
        $directories = [];
        $unused = [];

        foreach ($this->directories() as $directory => $label) {
            $count = 0;
            $bytes = 0;

            foreach ($disk->allFiles($directory) as $path) {
                if (str_starts_with(basename($path), '.')) {
                    continue;
                }

                $size = $disk->size($path);
                $count++;
                $bytes += $size;

                if (! isset($referenced[$path])) {
                    $unused[] = ['path' => $path, 'bytes' => $size, 'modified' => $disk->lastModified($path)];
                }
            }

            $directories[] = ['label' => $label, 'directory' => $directory, 'count' => $count, 'bytes' => $bytes];
        }

        usort($unused, fn (array $a, array $b) => $b['modified'] <=> $a['modified']);

        return [
            'directories' => $directories,
            'total_count' => array_sum(array_column($directories, 'count')),
            'total_bytes' => array_sum(array_column($directories, 'bytes')),
            'unused_count' => count($unused),
            'unused_bytes' => array_sum(array_column($unused, 'bytes')),
            'unused_items' => array_column(array_slice($unused, 0, self::UNUSED_ITEM_LIMIT), 'path'),
            'calculated_at' => now()->format('Y/m/d H:i'),
        ];
    }

    /**
     * 画像の保存先と表示名。
     *
     * @return array<string, string>
     */
    private function directories(): array
    {
        return [
            Article::THUMBNAIL_DIRECTORY => __('サムネイル'),
            Article::CONTENT_IMAGE_DIRECTORY => __('本文の画像'),
            SinglePage::HEADER_IMAGE_DIRECTORY => __('ヘッダー画像'),
            GalleryImage::IMAGE_DIRECTORY => __('ギャラリー'),
            TopSliderImage::IMAGE_DIRECTORY => __('トップスライダー'),
            UserDetail::USER_IMAGE_DIRECTORY => __('ユーザーのアイコン'),
            SiteSetting::SITE_ICON_DIRECTORY => __('サイトアイコン'),
            SiteSetting::SITE_IMAGE_DIRECTORY => __('サイト画像'),
            PageBuilder::IMAGE_DIRECTORY => __('ページビルダー'),
        ];
    }

    /**
     * レコードから参照されている画像のパス(image/ から始まる公開ディスク基準のパス)の集合。
     * 画像の列と、本文の HTML に含まれる画像の URL のどちらも、行の値の中の「image/…」を拾う。
     *
     * @return array<string, true>
     */
    private function referencedPaths(): array
    {
        $tables = self::REFERENCING_TABLES;

        foreach (CustomPageType::withTrashed()->get() as $type) {
            array_push($tables, ...$type->tableNames());
        }

        $paths = [];

        foreach (array_unique($tables) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (DB::table($table)->orderBy('id')->lazy() as $row) {
                $values = implode("\n", array_filter((array) $row, 'is_string'));

                if (! str_contains($values, 'image/')) {
                    continue;
                }

                preg_match_all('#image/[A-Za-z0-9_\-./]+\.[A-Za-z0-9]+#', $values, $matches);

                foreach ($matches[0] as $path) {
                    $paths[$path] = true;
                }
            }
        }

        return $paths;
    }
}
