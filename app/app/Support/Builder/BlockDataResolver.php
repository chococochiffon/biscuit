<?php

namespace App\Support\Builder;

use App\Http\Resources\ArticleResource;
use App\Http\Resources\GalleryImageResource;
use App\Models\GalleryImage;
use App\Models\LayoutBlock;
use App\Models\SinglePage;
use App\Support\CallContent\SinglePageContentSource;
use Illuminate\Database\Eloquent\Collection;

/**
 * CMS のデータを表示するブロック(BlockRegistry の category が cms)の data を、ブロックの props(取得の条件)から取得する。
 * 保存する内容には条件だけを持ち、公開側に返すとき(BuilderPresenter::forPublic())と、エディタの Canvas の見本で取得する。
 * - article-list: articles(条件どおりの公開中の記事。ArticleListQuery)
 * - navigation: items(label・path・prefix。サイトのナビメニューか、リンクリストに表示する固定ページ)
 * - gallery: images(条件どおりの公開中のギャラリー画像。並び順)
 */
final class BlockDataResolver
{
    /**
     * ブロックの data(CMS のデータを表示するブロックでなければ null)。
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>|null
     */
    public static function dataFor(string $type, array $props): ?array
    {
        return match ($type) {
            'article-list' => ['articles' => ArticleResource::collection(ArticleListQuery::articles($props))->resolve()],
            'navigation' => ['items' => self::navigationItems($props)],
            'gallery' => ['images' => GalleryImageResource::collection(self::galleryImages($props))->resolve()],
            default => null,
        };
    }

    /**
     * ギャラリーの画像。公開中の画像を並び順で、分類(category。null はすべて)で絞り込み、件数(limit)まで。
     *
     * @param  array<string, mixed>  $props
     * @return Collection<int, GalleryImage>
     */
    public static function galleryImages(array $props): Collection
    {
        $category = is_int($props['category'] ?? null) ? $props['category'] : null;
        $limit = is_int($props['limit'] ?? null) ? max(1, min(48, $props['limit'])) : 8;

        return GalleryImage::query()
            ->published()
            ->with('category')
            ->when($category !== null, fn ($query) => $query->where('gallery_category_id', $category))
            ->ordered()
            ->limit($limit)
            ->get();
    }

    /**
     * ナビゲーションの項目。site はレイアウト管理のナビメニューと同じ項目、pages はリンクリストに表示する公開中の固定ページ。
     *
     * @param  array<string, mixed>  $props
     * @return list<array{label: string, path: string, prefix: bool}>
     */
    public static function navigationItems(array $props): array
    {
        if (($props['source'] ?? 'site') === 'pages') {
            return (new SinglePageContentSource)->getLinkList()
                ->map(fn (SinglePage $page) => ['label' => $page->title, 'path' => $page->path, 'prefix' => false])
                ->values()
                ->all();
        }

        return LayoutBlock::siteNavMenuItems();
    }
}
