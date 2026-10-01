<?php

namespace App\Support\Builder;

use App\Support\HtmlSanitizer;

/**
 * ページビルダーの内容(ノードの木の JSON)を、BlockRegistry・StyleRegistry の定義に照らして検証・整形する。
 * 管理画面のエディタも同じ定義で入力を制限するが、送られてきた JSON は信用せずここで必ず確かめる。
 *
 * errors() はエラーの一覧(空なら正しい)を返し、各エラーには対象のノードの ID(分からなければ null)を含める
 * (エディタがそのノードを選択して知らせる)。normalize() は正しい内容の props に既定値を補い、テキストの HTML を無害化する。
 */
final class BuilderValidator
{
    /**
     * 中身を確かめる前の、ノードに書けるキー。
     *
     * @var list<string>
     */
    private const NODE_KEYS = ['id', 'type', 'props', 'styles', 'responsive', 'children'];

    /**
     * URL の項目に入れられるリンク先(http(s)・mailto・tel・サイト内の / 始まり・ページ内の #)。
     * // で始まるプロトコル相対 URL と、ブラウザが / と同じに扱うことがある \ は受け付けない。
     */
    private const URL_PATTERN = '#\A(?:https?://[^\s\\\\]+|mailto:[^\s\\\\]+|tel:[0-9+\-() ]+|/(?!/)[^\s\\\\]*|\#[^\s\\\\]*)\z#i';

    /**
     * URL の最大文字数。
     */
    private const URL_MAX_LENGTH = 2048;

    /**
     * @var list<array{node: string|null, message: string}>
     */
    private array $errors = [];

    /**
     * 検証済みのノードの ID(重複の確認用)。
     *
     * @var array<string, true>
     */
    private array $ids = [];

    private int $nodeCount = 0;

    /**
     * 内容を検証し、エラーの一覧を返す(空なら正しい)。
     *
     * @return list<array{node: string|null, message: string}>
     */
    public function errors(mixed $content): array
    {
        $this->errors = [];
        $this->ids = [];
        $this->nodeCount = 0;

        if (! self::isObject($content) || array_diff(array_keys($content), ['version', 'children']) !== []) {
            return [$this->error(null, __('ビルダーの内容の形式が正しくありません。'))];
        }

        if (($content['version'] ?? null) !== SchemaMigrator::CURRENT_VERSION) {
            return [$this->error(null, __('ビルダーの内容の版(:version)には対応していません。', ['version' => json_encode($content['version'] ?? null)]))];
        }

        $this->validateChildren($content['children'] ?? null, null, null);

        $max = (int) config('limits.builder_nodes');

        if ($this->nodeCount > $max) {
            $this->errors[] = $this->error(null, __('ブロックの数が多すぎます(最大 :max 個)。', ['max' => $max]));
        }

        return $this->errors;
    }

    /**
     * 検証済みの内容を整形して返す。props は定義の既定値に重ね、テキストの HTML は無害化する。
     * errors() が空の内容だけを渡すこと。
     *
     * @param  array<string, mixed>  $content
     * @return array{version: int, children: list<array<string, mixed>>}
     */
    public function normalize(array $content): array
    {
        return [
            'version' => SchemaMigrator::CURRENT_VERSION,
            'children' => array_map($this->normalizeNode(...), $content['children']),
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function normalizeNode(array $node): array
    {
        $definition = BlockRegistry::get($node['type']);
        $props = [...BlockRegistry::defaultProps($node['type']), ...($node['props'] ?? [])];

        foreach ($definition['props'] as $name => $prop) {
            if ($prop['type'] === 'richtext') {
                $props[$name] = HtmlSanitizer::clean($props[$name]) ?? '';
            }
        }

        $normalized = [
            'id' => $node['id'],
            'type' => $node['type'],
            'props' => $props,
            'styles' => $node['styles'] ?? [],
        ];

        $responsive = array_filter($node['responsive'] ?? [], fn (array $styles) => $styles !== []);

        if ($responsive !== []) {
            $normalized['responsive'] = $responsive;
        }

        if ($definition['children'] !== []) {
            $normalized['children'] = array_map($this->normalizeNode(...), $node['children'] ?? []);
        }

        return $normalized;
    }

    /**
     * 親(null はページの直下)の子の一覧を検証する。
     */
    private function validateChildren(mixed $children, ?string $parentType, ?string $parentId): void
    {
        if (! is_array($children) || ! array_is_list($children)) {
            $this->errors[] = $this->error($parentId, __('ビルダーの内容の形式が正しくありません。'));

            return;
        }

        foreach ($children as $child) {
            $this->validateNode($child, $parentType);
        }
    }

    private function validateNode(mixed $node, ?string $parentType): void
    {
        if (! self::isObject($node)) {
            $this->errors[] = $this->error(null, __('ノードの形式が正しくありません。'));

            return;
        }

        $id = is_string($node['id'] ?? null) && preg_match(BuilderContent::ID_PATTERN, $node['id']) === 1 ? $node['id'] : null;
        $type = $node['type'] ?? null;

        if (! is_string($type) || ! BlockRegistry::has($type)) {
            $this->errors[] = $this->error($id, __('種類「:type」のブロックはありません。', ['type' => is_string($type) ? $type : json_encode($type)]));

            return;
        }

        $definition = BlockRegistry::get($type);
        $label = __($definition['label']);

        if ($id === null || ! str_starts_with($id, $type.'_')) {
            $this->errors[] = $this->error(null, __('「:block」の ID の形式が正しくありません。', ['block' => $label]));
            $id = null;
        } elseif (isset($this->ids[$id])) {
            $this->errors[] = $this->error($id, __('「:block」の ID が重複しています。', ['block' => $label]));
        } else {
            $this->ids[$id] = true;
        }

        if (! BlockRegistry::allowsChild($parentType, $type)) {
            $this->errors[] = $this->error($id, $parentType === null
                ? __('「:child」はページの直下に置けません。', ['child' => $label])
                : __('「:child」は「:parent」の中に置けません。', ['child' => $label, 'parent' => __(BlockRegistry::get($parentType)['label'])]));

            // 置けない場所のブロックの中身は確かめない(構造の定義から外れた深い入れ子をたどらないようにする)
            return;
        }

        $this->nodeCount++;

        foreach (array_diff(array_keys($node), self::NODE_KEYS) as $key) {
            $this->errors[] = $this->error($id, __('「:block」に「:name」という項目はありません。', ['block' => $label, 'name' => $key]));
        }

        $this->validateProps($node['props'] ?? [], $definition['props'], $id, $label);
        $this->validateStyles($node['styles'] ?? [], $definition['styles'], $id, $label);
        $this->validateResponsive($node['responsive'] ?? [], $definition['styles'], $id, $label);

        if ($definition['children'] === []) {
            if (($node['children'] ?? []) !== []) {
                $this->errors[] = $this->error($id, __('「:block」の中には何も置けません。', ['block' => $label]));
            }

            return;
        }

        $this->validateChildren($node['children'] ?? [], $type, $id);
    }

    /**
     * @param  array<string, array<string, mixed>>  $definitions
     */
    private function validateProps(mixed $props, array $definitions, ?string $id, string $label): void
    {
        if (! self::isObject($props)) {
            $this->errors[] = $this->error($id, __('「:block」の項目の形式が正しくありません。', ['block' => $label]));

            return;
        }

        foreach ($props as $name => $value) {
            if (! isset($definitions[$name])) {
                $this->errors[] = $this->error($id, __('「:block」に「:name」という項目はありません。', ['block' => $label, 'name' => $name]));
            } elseif (! $this->isValidProp($definitions[$name], $value)) {
                $this->errors[] = $this->error($id, __('「:block」の「:name」の値が正しくありません。', ['block' => $label, 'name' => $name]));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function isValidProp(array $definition, mixed $value): bool
    {
        if ($value === null) {
            return ($definition['nullable'] ?? false) || in_array($definition['type'], ['url', 'image'], true);
        }

        return match ($definition['type']) {
            'string', 'richtext' => is_string($value) && mb_strlen($value) <= $definition['max'],
            'int' => is_int($value) && $value >= $definition['min'] && $value <= $definition['max'],
            'enum' => in_array($value, $definition['options'], true),
            'bool' => is_bool($value),
            'url' => is_string($value) && strlen($value) <= self::URL_MAX_LENGTH && preg_match(self::URL_PATTERN, $value) === 1,
            'image' => is_string($value) && preg_match(BuilderContent::IMAGE_PATH_PATTERN, $value) === 1,
            default => false,
        };
    }

    /**
     * @param  list<string>  $allowed
     */
    private function validateStyles(mixed $styles, array $allowed, ?string $id, string $label): void
    {
        if (! self::isObject($styles)) {
            $this->errors[] = $this->error($id, __('「:block」のスタイルの形式が正しくありません。', ['block' => $label]));

            return;
        }

        foreach ($styles as $name => $value) {
            if (! in_array($name, $allowed, true)) {
                $this->errors[] = $this->error($id, __('「:block」に「:name」というスタイルは使えません。', ['block' => $label, 'name' => $name]));
            } elseif (! StyleRegistry::isValid($name, $value)) {
                $this->errors[] = $this->error($id, __('「:block」のスタイル「:name」の値が正しくありません。', ['block' => $label, 'name' => $name]));
            }
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    private function validateResponsive(mixed $responsive, array $allowed, ?string $id, string $label): void
    {
        if (! self::isObject($responsive)) {
            $this->errors[] = $this->error($id, __('「:block」のスタイルの形式が正しくありません。', ['block' => $label]));

            return;
        }

        foreach ($responsive as $device => $styles) {
            if (! in_array($device, StyleRegistry::DEVICES, true)) {
                $this->errors[] = $this->error($id, __('端末「:device」の設定はありません。', ['device' => $device]));

                continue;
            }

            $this->validateStyles($styles, $allowed, $id, $label);
        }
    }

    /**
     * JSON のオブジェクトにあたる配列か(空のオブジェクトは PHP では空の配列になるため、空の配列も含める)。
     *
     * @phpstan-assert-if-true array<string, mixed> $value
     */
    private static function isObject(mixed $value): bool
    {
        return is_array($value) && ($value === [] || ! array_is_list($value));
    }

    /**
     * @return array{node: string|null, message: string}
     */
    private function error(?string $node, string $message): array
    {
        return ['node' => $node, 'message' => $message];
    }
}
