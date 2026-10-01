<?php

namespace App\Support\Builder;

/**
 * ページビルダーのブロックの定義(種類・置ける子・props・使えるスタイル)。
 * 保存時の検証(BuilderValidator)と管理画面のエディタ(toArray() を JSON で渡す)のどちらもここを元にする。
 * 置ける親(allowedParents)は allowedChildren を逆に引いて作り、定義は片側だけに書く。
 *
 * props の各項目は type(string・richtext・int・enum・url・image)と default を持ち、
 * string・richtext は max(文字数)、int は min/max、enum は options を持つ。nullable の項目は null も受け付ける(url・image は常に null 可)。
 */
final class BlockRegistry
{
    /**
     * ページの直下(ルート)に置けるブロック。
     *
     * @var list<string>
     */
    public const ROOT_CHILDREN = ['section'];

    /**
     * 中に何も置けない基本のブロック。
     *
     * @var list<string>
     */
    private const BASIC_BLOCKS = ['heading', 'text', 'image', 'button', 'spacer', 'divider'];

    /**
     * 余白のスタイル。
     *
     * @var list<string>
     */
    private const SPACING_STYLES = ['marginTop', 'marginBottom', 'paddingTop', 'paddingBottom', 'paddingLeft', 'paddingRight'];

    /**
     * 上下の外側の余白のスタイル。
     *
     * @var list<string>
     */
    private const MARGIN_STYLES = ['marginTop', 'marginBottom'];

    /**
     * ブロックの種類ごとの定義(表示名は日本語の原文。toArray() で翻訳する)。
     *
     * @return array<string, array{label: string, category: string, icon: string, children: list<string>, props: array<string, array<string, mixed>>, styles: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            'section' => [
                'label' => 'セクション',
                'category' => 'layout',
                'icon' => 'square',
                'children' => ['container', 'row', ...self::BASIC_BLOCKS],
                'props' => [
                    'backgroundImage' => ['type' => 'image', 'default' => null],
                ],
                'styles' => [...self::SPACING_STYLES, 'minHeight', 'backgroundColor', 'color', 'textAlign'],
            ],
            'container' => [
                'label' => 'コンテナ',
                'category' => 'layout',
                'icon' => 'bounding-box',
                'children' => ['row', ...self::BASIC_BLOCKS],
                'props' => [],
                'styles' => [...self::SPACING_STYLES, 'maxWidth', 'backgroundColor', 'textAlign'],
            ],
            'row' => [
                'label' => '行',
                'category' => 'layout',
                'icon' => 'layout-three-columns',
                'children' => ['column'],
                'props' => [
                    // カラムの間の余白(Bootstrap の gutter と同じ 0〜5 の段階)
                    'gap' => ['type' => 'int', 'default' => 3, 'min' => 0, 'max' => 5],
                ],
                'styles' => self::MARGIN_STYLES,
            ],
            'column' => [
                'label' => 'カラム',
                'category' => 'layout',
                'icon' => 'layout-split',
                'children' => self::BASIC_BLOCKS,
                'props' => [
                    // 12 分割の幅。タブレット・スマートフォンは未指定(null)なら、タブレットはデスクトップと同じ・スマートフォンは 12
                    'span' => ['type' => 'int', 'default' => 12, 'min' => 1, 'max' => 12],
                    'spanTablet' => ['type' => 'int', 'default' => null, 'min' => 1, 'max' => 12, 'nullable' => true],
                    'spanMobile' => ['type' => 'int', 'default' => null, 'min' => 1, 'max' => 12, 'nullable' => true],
                ],
                'styles' => [...self::SPACING_STYLES, 'backgroundColor', 'textAlign', 'borderRadius'],
            ],
            'heading' => [
                'label' => '見出し',
                'category' => 'basic',
                'icon' => 'type-h1',
                'children' => [],
                'props' => [
                    'text' => ['type' => 'string', 'default' => '見出し', 'max' => 200],
                    'level' => ['type' => 'int', 'default' => 2, 'min' => 1, 'max' => 6],
                ],
                'styles' => [...self::MARGIN_STYLES, 'color', 'fontSize', 'fontWeight', 'lineHeight', 'textAlign'],
            ],
            'text' => [
                'label' => 'テキスト',
                'category' => 'basic',
                'icon' => 'text-paragraph',
                'children' => [],
                'props' => [
                    // Quill で入力した HTML(保存時に HtmlSanitizer::clean() で無害化する)
                    'html' => ['type' => 'richtext', 'default' => '<p>テキスト</p>', 'max' => 20000],
                ],
                'styles' => [...self::MARGIN_STYLES, 'color', 'fontSize', 'lineHeight', 'textAlign'],
            ],
            'image' => [
                'label' => '画像',
                'category' => 'basic',
                'icon' => 'image',
                'children' => [],
                'props' => [
                    'src' => ['type' => 'image', 'default' => null],
                    'alt' => ['type' => 'string', 'default' => '', 'max' => 200],
                    'href' => ['type' => 'url', 'default' => null],
                ],
                'styles' => [...self::MARGIN_STYLES, 'width', 'maxWidth', 'borderRadius', 'textAlign'],
            ],
            'button' => [
                'label' => 'ボタン',
                'category' => 'basic',
                'icon' => 'hand-index',
                'children' => [],
                'props' => [
                    'text' => ['type' => 'string', 'default' => 'ボタン', 'max' => 100],
                    'href' => ['type' => 'url', 'default' => '#'],
                    'target' => ['type' => 'enum', 'default' => '_self', 'options' => ['_self', '_blank']],
                    'variant' => ['type' => 'enum', 'default' => 'primary', 'options' => ['primary', 'secondary', 'outline-primary', 'outline-secondary', 'link']],
                ],
                'styles' => [...self::MARGIN_STYLES, 'textAlign', 'color', 'backgroundColor', 'borderRadius', 'fontSize'],
            ],
            'spacer' => [
                'label' => 'スペーサー',
                'category' => 'basic',
                'icon' => 'arrows-vertical',
                'children' => [],
                'props' => [
                    // 高さ(px)
                    'height' => ['type' => 'int', 'default' => 32, 'min' => 0, 'max' => 400],
                ],
                'styles' => [],
            ],
            'divider' => [
                'label' => '区切り線',
                'category' => 'basic',
                'icon' => 'hr',
                'children' => [],
                'props' => [],
                'styles' => [...self::MARGIN_STYLES, 'borderColor', 'borderWidth', 'borderStyle'],
            ],
        ];
    }

    /**
     * 指定した種類の定義(未定義なら null)。
     *
     * @return array{label: string, category: string, icon: string, children: list<string>, props: array<string, array<string, mixed>>, styles: list<string>}|null
     */
    public static function get(string $type): ?array
    {
        return self::definitions()[$type] ?? null;
    }

    /**
     * 指定した種類のブロックが定義されているか。
     */
    public static function has(string $type): bool
    {
        return array_key_exists($type, self::definitions());
    }

    /**
     * 親(null はページの直下)の中に、指定した種類のブロックを置けるか。
     */
    public static function allowsChild(?string $parentType, string $childType): bool
    {
        $children = $parentType === null ? self::ROOT_CHILDREN : (self::get($parentType)['children'] ?? []);

        return in_array($childType, $children, true);
    }

    /**
     * 指定した種類のブロックの props の既定値。
     *
     * @return array<string, mixed>
     */
    public static function defaultProps(string $type): array
    {
        return array_map(fn (array $prop) => $prop['default'], self::get($type)['props'] ?? []);
    }

    /**
     * 管理画面のエディタに渡す定義(表示名は現在の言語に翻訳し、置ける親を足す)。
     *
     * @return array{rootChildren: list<string>, blocks: array<string, array<string, mixed>>, styles: array<string, string|list<string>>}
     */
    public static function toArray(): array
    {
        $definitions = self::definitions();
        $blocks = [];

        foreach ($definitions as $type => $definition) {
            $parents = array_keys(array_filter($definitions, fn (array $parent) => in_array($type, $parent['children'], true)));

            if (in_array($type, self::ROOT_CHILDREN, true)) {
                array_unshift($parents, null);
            }

            $blocks[$type] = [
                ...$definition,
                'label' => __($definition['label']),
                'allowedParents' => $parents,
            ];
        }

        return [
            'rootChildren' => self::ROOT_CHILDREN,
            'blocks' => $blocks,
            'styles' => StyleRegistry::kinds(),
        ];
    }
}
