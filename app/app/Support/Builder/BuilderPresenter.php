<?php

namespace App\Support\Builder;

use App\Models\PageBuilder;
use App\Models\PageBuilderTheme;
use stdClass;

/**
 * ページビルダーの内容(ノードの木)を、管理画面のエディタ・公開側に返す形に整える。
 * どちらも SchemaMigrator で今の版にそろえ、props・styles・responsive は空でも JSON のオブジェクト({})で返す
 * (PHP では空のオブジェクトが空の配列になり、そのままでは [] で返ってしまうため)。
 * 公開側に返すときは、画像の項目を公開ディスク基準のパスから公開 URL に置き換え、CMS のデータを表示するブロック(記事一覧・ナビゲーション)には
 * 取得の条件どおりのデータを data に入れる(保存する内容には条件だけを持ち、データは返すときに BlockDataResolver で取得する)。
 * 公開側では、表示する期間(Visibility)の外のブロックを取り除き、表示条件は端末の条件(hideOn)だけを返す。
 */
final class BuilderPresenter
{
    /**
     * 管理画面のエディタに返す内容(画像は保存したときのパスのまま)。
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function forEditor(array $content): array
    {
        return self::present($content, forPublic: false);
    }

    /**
     * 公開側(chococo)に返す内容(画像は公開 URL)。テーマ(色・フォント)を theme に入れる。
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function forPublic(array $content): array
    {
        return [...self::present($content, forPublic: true), 'theme' => PageBuilderTheme::current()->toPresentation()];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private static function present(array $content, bool $forPublic): array
    {
        $content = (new SchemaMigrator)->migrate($content);

        $presented = [
            'version' => $content['version'],
            'children' => self::presentChildren($content['children'] ?? [], $forPublic),
        ];

        // ページ・コンポーネントの Custom CSS(公開側は .page-builder の中にネストして効かせる)
        if (CustomCss::normalize($content['css'] ?? null) !== null) {
            $presented['css'] = $content['css'];
        }

        return $presented;
    }

    /**
     * 子の並び。公開側では、表示する期間の外のブロックを(子ごと)取り除く。
     *
     * @param  list<array<string, mixed>>  $children
     * @return list<array<string, mixed>>
     */
    private static function presentChildren(array $children, bool $forPublic): array
    {
        if ($forPublic) {
            $children = array_filter($children, fn (array $node) => Visibility::isWithinPeriod($node));
        }

        return array_values(array_map(fn (array $node) => self::presentNode($node, $forPublic), $children));
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function presentNode(array $node, bool $forPublic): array
    {
        $props = $node['props'] ?? [];

        $presentedProps = $props;

        foreach (BlockRegistry::get($node['type'])['props'] ?? [] as $name => $prop) {
            if ($forPublic && $prop['type'] === 'image' && is_string($props[$name] ?? null)) {
                $presentedProps[$name] = PageBuilder::publicImageUrl($props[$name]);
            }

            // 独自コンポーネントの差し替えた値は、空でも JSON のオブジェクトで返す(data の取得には配列のまま渡す)
            if ($prop['type'] === 'overrides' && is_array($props[$name] ?? null)) {
                $presentedProps[$name] = self::object($props[$name]);
            }
        }

        $presented = [
            'id' => $node['id'],
            'type' => $node['type'],
            'props' => self::object($presentedProps),
            'styles' => self::object($node['styles'] ?? []),
        ];

        if (($node['responsive'] ?? []) !== []) {
            $presented['responsive'] = self::object(array_map(self::object(...), $node['responsive']));
        }

        // 表示条件: エディタにはそのまま返し、公開側には端末の条件だけを返す(期間は返す前に取り除いて済ませている)
        $visibility = $node['visibility'] ?? [];

        if ($forPublic && ($visibility['hideOn'] ?? []) !== []) {
            $presented['visibility'] = ['hideOn' => $visibility['hideOn']];
        } elseif (! $forPublic && $visibility !== []) {
            $presented['visibility'] = self::object($visibility);
        }

        // ブロックの追加のクラス名(Custom CSS から狙う)
        if (($node['classes'] ?? []) !== []) {
            $presented['classes'] = $node['classes'];
        }

        // 独自コンポーネントの差し替えられる項目(エディタだけ。公開側は差し替えた値を当てはめて返すため要らない)
        if (! $forPublic && ($node['exposed'] ?? []) !== []) {
            $presented['exposed'] = self::object($node['exposed']);
        }

        if ($forPublic && ($data = BlockDataResolver::dataFor($node['type'], $props)) !== null) {
            $presented['data'] = $data;
        }

        if (array_key_exists('children', $node)) {
            $presented['children'] = self::presentChildren($node['children'], $forPublic);
        }

        return $presented;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function object(array $values): stdClass
    {
        return (object) $values;
    }
}
