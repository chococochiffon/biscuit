<?php

namespace App\Support\Builder;

use App\Models\PageBuilder;
use stdClass;

/**
 * ページビルダーの内容(ノードの木)を、管理画面のエディタ・公開側に返す形に整える。
 * どちらも SchemaMigrator で今の版にそろえ、props・styles・responsive は空でも JSON のオブジェクト({})で返す
 * (PHP では空のオブジェクトが空の配列になり、そのままでは [] で返ってしまうため)。
 * 公開側に返すときは、画像の項目を公開ディスク基準のパスから公開 URL に置き換え、CMS のデータを表示するブロック(記事一覧・ナビゲーション)には
 * 取得の条件どおりのデータを data に入れる(保存する内容には条件だけを持ち、データは返すときに BlockDataResolver で取得する)。
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
     * 公開側(chococo)に返す内容(画像は公開 URL)。
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function forPublic(array $content): array
    {
        return self::present($content, forPublic: true);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private static function present(array $content, bool $forPublic): array
    {
        $content = (new SchemaMigrator)->migrate($content);

        return [
            'version' => $content['version'],
            'children' => array_map(fn (array $node) => self::presentNode($node, $forPublic), $content['children'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function presentNode(array $node, bool $forPublic): array
    {
        $props = $node['props'] ?? [];

        if ($forPublic) {
            foreach (BlockRegistry::get($node['type'])['props'] ?? [] as $name => $prop) {
                if ($prop['type'] === 'image' && is_string($props[$name] ?? null)) {
                    $props[$name] = PageBuilder::publicImageUrl($props[$name]);
                }
            }
        }

        $presented = [
            'id' => $node['id'],
            'type' => $node['type'],
            'props' => self::object($props),
            'styles' => self::object($node['styles'] ?? []),
        ];

        if (($node['responsive'] ?? []) !== []) {
            $presented['responsive'] = self::object(array_map(self::object(...), $node['responsive']));
        }

        if ($forPublic && ($data = BlockDataResolver::dataFor($node['type'], $props)) !== null) {
            $presented['data'] = $data;
        }

        if (array_key_exists('children', $node)) {
            $presented['children'] = array_map(fn (array $child) => self::presentNode($child, $forPublic), $node['children']);
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
