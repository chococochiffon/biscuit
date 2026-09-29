<?php

namespace App\Support;

use App\Models\Article;
use App\Models\CustomPages\CustomPageEntry;
use App\Models\CustomPageType;
use App\Models\SinglePage;

/**
 * 公開側のパンくず(パス解決 API の breadcrumbs)を組み立てる。
 * 各項目は label と path(リンクしない項目は null)を持ち、先頭は Home、末尾は表示中のページ。
 */
class Breadcrumbs
{
    public const HOME_LABEL = 'Home';

    /**
     * 記事・固定ページのパンくず。途中の階層(例: /blog/life/spring-cafe の /blog・/blog/life)は、
     * そのパスに公開中の固定ページか記事があればタイトルでリンクし、なければ URL の文字列をリンクなしで出す。
     *
     * @return list<array{label: string, path: string|null}>
     */
    public static function forPage(string $path, string $title): array
    {
        $segments = explode('/', trim($path, '/'));
        array_pop($segments);

        $ancestors = [];
        $ancestorPath = '';

        foreach ($segments as $segment) {
            $ancestorPath .= '/'.$segment;
            $ancestors[$ancestorPath] = $segment;
        }

        $titles = self::publishedTitles(array_keys($ancestors));
        $items = [self::home()];

        foreach ($ancestors as $ancestorPath => $segment) {
            $items[] = isset($titles[$ancestorPath])
                ? ['label' => $titles[$ancestorPath], 'path' => $ancestorPath]
                : ['label' => $segment, 'path' => null];
        }

        $items[] = ['label' => $title, 'path' => $path];

        return $items;
    }

    /**
     * カスタムページの一覧(例: /recipes)のパンくず。
     *
     * @return list<array{label: string, path: string|null}>
     */
    public static function forCustomPageList(CustomPageType $type): array
    {
        return [self::home(), ['label' => $type->label, 'path' => $type->publicPath()]];
    }

    /**
     * カスタムページ 1 件(例: /recipes/nikujaga)のパンくず。途中の階層は種類の一覧にする。
     *
     * @return list<array{label: string, path: string|null}>
     */
    public static function forCustomPage(CustomPageType $type, CustomPageEntry $entry): array
    {
        return [...self::forCustomPageList($type), ['label' => $entry->title, 'path' => $entry->path()]];
    }

    /**
     * @return array{label: string, path: string}
     */
    private static function home(): array
    {
        return ['label' => self::HOME_LABEL, 'path' => '/'];
    }

    /**
     * パスごとの公開中のページのタイトル(パス解決と同じく、固定ページを記事より優先する)。
     *
     * @param  list<string>  $paths
     * @return array<string, string>
     */
    private static function publishedTitles(array $paths): array
    {
        if ($paths === []) {
            return [];
        }

        return [
            ...Article::query()->published()->whereIn('path', $paths)->pluck('title', 'path')->all(),
            ...SinglePage::query()->published()->whereIn('path', $paths)->pluck('title', 'path')->all(),
        ];
    }
}
