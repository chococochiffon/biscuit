<?php

namespace App\Support\Builder;

use App\Enums\BuilderComponentKind;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\GalleryImageResource;
use App\Models\GalleryImage;
use App\Models\LayoutBlock;
use App\Models\PageBuilderComponent;
use App\Models\SinglePage;
use App\Support\CallContent\SinglePageContentSource;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Collection;

/**
 * CMS のデータを表示するブロック(BlockRegistry の category が cms)と動画のブロックの data を、ブロックの props(取得の条件)から取得する。
 * 保存する内容には条件だけを持ち、公開側に返すとき(BuilderPresenter::forPublic())と、エディタの Canvas の見本で取得する。
 * - article-list: articles(条件どおりの公開中の記事。ArticleListQuery)
 * - navigation: items(label・path・prefix。サイトのナビメニューか、リンクリストに表示する固定ページ)
 * - gallery: images(条件どおりの公開中のギャラリー画像。並び順)
 * - video: embed_url(動画の URL から組み立てた埋め込み用の URL。VideoUrl)
 * - global: children(参照するグローバルコンポーネントの公開中の内容のノード。未公開・削除済みなら空)
 * - custom: children(参照する独自コンポーネントの公開中の内容に、差し替えた値を当てはめたノード。未公開・削除済みなら空)
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
            'video' => ['embed_url' => is_string($props['url'] ?? null) ? VideoUrl::embedUrl($props['url']) : null],
            'global' => ['children' => self::globalComponentChildren($props)],
            'custom' => ['children' => self::customComponentChildren($props)],
            default => null,
        };
    }

    /**
     * 解決中のグローバルコンポーネントの id(コンポーネントの中にコンポーネントがあっても、入れ子を終わらせるため)。
     *
     * @var array<int, true>
     */
    private static array $resolvingComponents = [];

    /**
     * グローバルコンポーネントの公開中の内容のノード(公開側の形)。未公開・削除済み・解決中(入れ子)なら空。
     *
     * @param  array<string, mixed>  $props
     * @return list<array<string, mixed>>
     */
    public static function globalComponentChildren(array $props): array
    {
        $id = is_int($props['component'] ?? null) ? $props['component'] : null;
        $component = $id === null || isset(self::$resolvingComponents[$id]) ? null : PageBuilderComponent::query()->find($id);

        if ($component?->published_content === null) {
            return [];
        }

        self::$resolvingComponents[$id] = true;

        try {
            return BuilderPresenter::forPublic($component->published_content)['children'];
        } finally {
            unset(self::$resolvingComponents[$id]);
        }
    }

    /**
     * 独自コンポーネントの公開中の内容に差し替えた値(values)を当てはめたノード(公開側の形)。未公開・削除済み・種類が違うなら空。
     * 値は部品の差し替えられる項目(exposed)にだけ当てはめ、項目の定義に合わない値・空の値は部品の値のまま。テキストの HTML は無害化する。
     *
     * @param  array<string, mixed>  $props
     * @return list<array<string, mixed>>
     */
    public static function customComponentChildren(array $props): array
    {
        $id = is_int($props['component'] ?? null) ? $props['component'] : null;
        $component = $id === null ? null : PageBuilderComponent::query()->where('kind', BuilderComponentKind::Custom)->find($id);

        if ($component?->published_content === null) {
            return [];
        }

        $values = is_array($props['values'] ?? null) ? $props['values'] : [];
        $content = $component->published_content;
        $content['children'] = self::applyOverrides($content['children'] ?? [], $values);

        return BuilderPresenter::forPublic($content)['children'];
    }

    /**
     * ノードの木に、差し替えた値(「ノードの ID.項目名」→ 値)を当てはめる。
     *
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $values
     * @return list<array<string, mixed>>
     */
    private static function applyOverrides(array $nodes, array $values): array
    {
        return array_map(function (array $node) use ($values) {
            $definitions = BlockRegistry::get($node['type'])['props'] ?? [];

            foreach (array_keys($node['exposed'] ?? []) as $name) {
                $value = $values[$node['id'].'.'.$name] ?? null;
                $definition = $definitions[$name] ?? null;

                if ($definition === null || $value === null || $value === '' || ! BuilderValidator::isValidPropValue($definition, $value)) {
                    continue;
                }

                $node['props'][$name] = $definition['type'] === 'richtext' ? (HtmlSanitizer::clean($value) ?? '') : $value;
            }

            if (isset($node['children'])) {
                $node['children'] = self::applyOverrides($node['children'], $values);
            }

            return $node;
        }, $nodes);
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
