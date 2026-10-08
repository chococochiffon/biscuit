<?php

namespace App\Support\Builder;

use Generator;
use Illuminate\Support\Str;

/**
 * ページビルダーの内容(ノードの木の JSON)を組み立てる・たどるための処理。
 *
 * 内容は { "version": 1, "children": [ノード, ...] } の形で、ノードは
 * { "id": "種類_ULID", "type": 種類, "props": {...}, "styles": {...}, "responsive"?: { "tablet"?: {...}, "mobile"?: {...} }, "layout"?: {...}, "children"?: [...] }。
 * layout は自由配置(v2)の面の直下のブロックの位置と大きさ(BuilderLayout)。
 * children は中にブロックを置ける種類(BlockRegistry の children が空でないもの)だけが持つ。
 */
final class BuilderContent
{
    /**
     * ノードの ID の形(種類 + _ + ULID)。
     */
    public const ID_PATTERN = '/\A[a-z]+(?:-[a-z]+)*_[0-9A-HJKMNP-TV-Z]{26}\z/';

    /**
     * 画像の props に入れられるパス(public ディスクの image/ 配下の画像。.. などで外へ出られないよう、ディレクトリ名に . を許さない)。
     */
    public const IMAGE_PATH_PATTERN = '#\Aimage/(?:[A-Za-z0-9_\-]+/)*[A-Za-z0-9_\-]+\.(?:png|jpe?g|gif|webp)\z#';

    /**
     * 何も置いていない内容。
     *
     * @return array{version: int, children: list<array<string, mixed>>}
     */
    public static function empty(): array
    {
        return ['version' => SchemaMigrator::CURRENT_VERSION, 'children' => []];
    }

    /**
     * 新しいノードの ID を発行する。
     */
    public static function newId(string $type): string
    {
        return $type.'_'.Str::ulid();
    }

    /**
     * 新しい ID を発行してノードを組み立てる。props は既定値に上書きで重ねる(シーダー・テスト用)。
     *
     * @param  array<string, mixed>  $props
     * @param  array<string, string>  $styles
     * @param  list<array<string, mixed>>  $children
     * @param  array<string, array<string, string>>  $responsive
     * @param  array<string, array<string, int|float>>  $layout  自由配置(v2)の位置と大きさ(BuilderLayout)
     * @return array<string, mixed>
     */
    public static function node(string $type, array $props = [], array $styles = [], array $children = [], array $responsive = [], array $layout = []): array
    {
        $node = [
            'id' => self::newId($type),
            'type' => $type,
            'props' => [...BlockRegistry::defaultProps($type), ...$props],
            'styles' => $styles,
        ];

        if ($responsive !== []) {
            $node['responsive'] = $responsive;
        }

        if ($layout !== []) {
            $node['layout'] = $layout;
        }

        if ((BlockRegistry::get($type)['children'] ?? []) !== []) {
            $node['children'] = $children;
        }

        return $node;
    }

    /**
     * 内容のすべてのノードを、親から子の順(深さ優先)にたどる。
     *
     * @param  array<string, mixed>  $content
     * @return Generator<int, array<string, mixed>>
     */
    public static function nodes(array $content): Generator
    {
        $stack = array_reverse(is_array($content['children'] ?? null) ? $content['children'] : []);

        while ($stack !== []) {
            $node = array_pop($stack);

            if (! is_array($node)) {
                continue;
            }

            yield $node;

            if (is_array($node['children'] ?? null)) {
                array_push($stack, ...array_reverse($node['children']));
            }
        }
    }

    /**
     * 内容が参照している画像(props の type が image の項目)のパスの一覧(重複なし)。
     *
     * @param  array<string, mixed>|null  $content
     * @return list<string>
     */
    public static function imagePaths(?array $content): array
    {
        $paths = [];

        foreach (self::nodes($content ?? []) as $node) {
            $definition = BlockRegistry::get((string) ($node['type'] ?? ''));

            foreach ($definition['props'] ?? [] as $name => $prop) {
                $value = $node['props'][$name] ?? null;

                if ($prop['type'] === 'image' && is_string($value) && $value !== '') {
                    $paths[$value] = true;
                }

                // 独自コンポーネントの差し替えた値の画像(値の型は部品の項目で決まるため、画像のパスの形のものを拾う)
                if ($prop['type'] === 'overrides' && is_array($value)) {
                    foreach ($value as $override) {
                        if (is_string($override) && preg_match(self::IMAGE_PATH_PATTERN, $override) === 1) {
                            $paths[$override] = true;
                        }
                    }
                }
            }
        }

        return array_keys($paths);
    }
}
