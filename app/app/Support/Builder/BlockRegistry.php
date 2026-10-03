<?php

namespace App\Support\Builder;

use App\Enums\BuilderContext;

/**
 * ページビルダーのブロックの定義(種類・置ける子・props・使えるスタイル)。
 * 保存時の検証(BuilderValidator)と管理画面のエディタ(toArray() を JSON で渡す)のどちらもここを元にする。
 * 置ける親(allowedParents)は allowedChildren を逆に引いて作り、定義は片側だけに書く。
 *
 * props の各項目は label(管理画面の入力欄の名前。日本語の原文)・type(string・richtext・int・enum・url・image・bool・video・overrides)と default を持ち、
 * string・richtext は max(文字数)、int は min/max、enum は options を持つ。nullable の項目は null も受け付ける(url・image・video は常に null 可)。
 * video は YouTube・Vimeo の動画の URL だけを受け付ける(VideoUrl)。overrides は独自コンポーネントの差し替えた値(「ノードの ID.項目名」→ 値)。
 * source はエディタで選択肢を登録済みのデータから作る項目(gallery-categories: ギャラリーの分類、global-components: グローバルコンポーネント、
 * custom-components: 独自コンポーネント)。
 */
final class BlockRegistry
{
    /**
     * ページの直下(ルート)に置けるブロック(セクションと、セクションの並びを差し込むグローバルコンポーネント)。
     *
     * @var list<string>
     */
    public const ROOT_CHILDREN = ['section', 'global'];

    /**
     * 中に何も置けない基本のブロック。
     *
     * @var list<string>
     */
    private const BASIC_BLOCKS = ['heading', 'text', 'image', 'button', 'spacer', 'divider'];

    /**
     * セクション・コンテナ・カラムの中に置けるブロック(基本のブロック・スライダーと、CMS のデータを表示するブロック)。
     * スライダーの中にはスライドだけを置け、ほかは中に何も置けない。
     *
     * @var list<string>
     */
    private const CONTENT_BLOCKS = [...self::BASIC_BLOCKS, 'video', 'slider', 'article-list', 'navigation', 'breadcrumb', 'gallery', 'custom'];

    /**
     * 独自コンポーネント(BuilderContext::CustomComponent)の一番外側に置けるブロック(カラムの中と同じブロックとコンテナ・行。
     * 独自コンポーネントの中には独自コンポーネントを置けない)。
     *
     * @var list<string>
     */
    public const CUSTOM_ROOT_CHILDREN = ['container', 'row', ...self::BASIC_BLOCKS, 'video', 'slider', 'article-list', 'navigation', 'breadcrumb', 'gallery'];

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
                'children' => ['container', 'row', ...self::CONTENT_BLOCKS],
                'props' => [
                    'backgroundImage' => ['label' => '背景画像', 'type' => 'image', 'default' => null],
                ],
                'styles' => [...self::SPACING_STYLES, 'minHeight', 'backgroundColor', 'color', 'textAlign'],
            ],
            'container' => [
                'label' => 'コンテナ',
                'category' => 'layout',
                'icon' => 'bounding-box',
                'children' => ['row', ...self::CONTENT_BLOCKS],
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
                    'gap' => ['label' => 'カラムの間の余白', 'type' => 'int', 'default' => 3, 'min' => 0, 'max' => 5],
                ],
                'styles' => self::MARGIN_STYLES,
            ],
            'column' => [
                'label' => 'カラム',
                'category' => 'layout',
                'icon' => 'layout-split',
                'children' => self::CONTENT_BLOCKS,
                'props' => [
                    // 12 分割の幅。タブレット・スマートフォンは未指定(null)なら、タブレットはデスクトップと同じ・スマートフォンは 12
                    'span' => ['label' => '幅(デスクトップ)', 'type' => 'int', 'default' => 12, 'min' => 1, 'max' => 12],
                    'spanTablet' => ['label' => '幅(タブレット)', 'type' => 'int', 'default' => null, 'min' => 1, 'max' => 12, 'nullable' => true],
                    'spanMobile' => ['label' => '幅(スマートフォン)', 'type' => 'int', 'default' => null, 'min' => 1, 'max' => 12, 'nullable' => true],
                ],
                'styles' => [...self::SPACING_STYLES, 'backgroundColor', 'textAlign', 'borderRadius'],
            ],
            'heading' => [
                'label' => '見出し',
                'category' => 'basic',
                'icon' => 'type-h1',
                'children' => [],
                'props' => [
                    'text' => ['label' => '文字', 'type' => 'string', 'default' => '見出し', 'max' => 200],
                    'level' => ['label' => '見出しのレベル', 'type' => 'int', 'default' => 2, 'min' => 1, 'max' => 6],
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
                    'html' => ['label' => '本文', 'type' => 'richtext', 'default' => '<p>テキスト</p>', 'max' => 20000],
                ],
                'styles' => [...self::MARGIN_STYLES, 'color', 'fontSize', 'lineHeight', 'textAlign'],
            ],
            'image' => [
                'label' => '画像',
                'category' => 'basic',
                'icon' => 'image',
                'children' => [],
                'props' => [
                    'src' => ['label' => '画像', 'type' => 'image', 'default' => null],
                    'alt' => ['label' => '代替テキスト', 'type' => 'string', 'default' => '', 'max' => 200],
                    'href' => ['label' => 'リンク先', 'type' => 'url', 'default' => null],
                ],
                'styles' => [...self::MARGIN_STYLES, 'width', 'maxWidth', 'borderRadius', 'textAlign'],
            ],
            'button' => [
                'label' => 'ボタン',
                'category' => 'basic',
                'icon' => 'hand-index',
                'children' => [],
                'props' => [
                    'text' => ['label' => 'ボタンの文字', 'type' => 'string', 'default' => 'ボタン', 'max' => 100],
                    'href' => ['label' => 'リンク先', 'type' => 'url', 'default' => '#'],
                    'target' => ['label' => '開き方', 'type' => 'enum', 'default' => '_self', 'options' => ['_self', '_blank']],
                    'variant' => ['label' => '見た目', 'type' => 'enum', 'default' => 'primary', 'options' => ['primary', 'secondary', 'outline-primary', 'outline-secondary', 'link']],
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
                    'height' => ['label' => '高さ(px)', 'type' => 'int', 'default' => 32, 'min' => 0, 'max' => 400],
                ],
                'styles' => [],
            ],
            // 動画: YouTube・Vimeo の URL を保存し、公開側に返すときに動画の ID から組み立てた埋め込み用の URL を入れる(BlockDataResolver)
            'video' => [
                'label' => '動画',
                'category' => 'basic',
                'icon' => 'play-btn',
                'children' => [],
                'props' => [
                    'url' => ['label' => '動画の URL(YouTube・Vimeo)', 'type' => 'video', 'default' => null],
                    'title' => ['label' => '動画のタイトル(読み上げ用)', 'type' => 'string', 'default' => '動画', 'max' => 200],
                    'aspect' => ['label' => '縦横比', 'type' => 'enum', 'default' => '16x9', 'options' => ['16x9', '4x3', '1x1', '21x9']],
                ],
                'styles' => [...self::MARGIN_STYLES, 'maxWidth'],
            ],
            // スライダー: 中に置いたスライド(画像)を順に切り替えて表示する。スライドはスライダーの中にだけ置ける
            'slider' => [
                'label' => 'スライダー',
                'category' => 'basic',
                'icon' => 'collection',
                'children' => ['slide'],
                'props' => [
                    'aspect' => ['label' => '縦横比', 'type' => 'enum', 'default' => '16x9', 'options' => ['16x9', '21x9', '4x3', '1x1']],
                    'autoplay' => ['label' => '自動で切り替える', 'type' => 'bool', 'default' => true],
                    'interval' => ['label' => '切り替える間隔(秒)', 'type' => 'int', 'default' => 5, 'min' => 2, 'max' => 30],
                    'showControls' => ['label' => '前後のボタンを表示する', 'type' => 'bool', 'default' => true],
                    'showIndicators' => ['label' => 'インジケーターを表示する', 'type' => 'bool', 'default' => true],
                ],
                'styles' => [...self::MARGIN_STYLES, 'maxWidth', 'borderRadius'],
            ],
            'slide' => [
                'label' => 'スライド',
                'category' => 'basic',
                'icon' => 'card-image',
                'children' => [],
                'props' => [
                    'src' => ['label' => '画像', 'type' => 'image', 'default' => null],
                    'alt' => ['label' => '代替テキスト', 'type' => 'string', 'default' => '', 'max' => 200],
                    'href' => ['label' => 'リンク先', 'type' => 'url', 'default' => null],
                ],
                'styles' => [],
            ],
            // 記事一覧: 保存するのは取得の条件だけで、公開側に返すときに条件どおりの公開中の記事を入れる(ArticleListQuery)
            'article-list' => [
                'label' => '記事一覧',
                'category' => 'cms',
                'icon' => 'newspaper',
                'children' => [],
                'props' => [
                    'limit' => ['label' => '表示件数', 'type' => 'int', 'default' => 6, 'min' => 1, 'max' => 20],
                    'order' => ['label' => '並び順', 'type' => 'enum', 'default' => 'newest', 'options' => ['newest', 'oldest']],
                    'parentPath' => ['label' => '投稿先で絞り込む(親パス。例: news)', 'type' => 'string', 'default' => '', 'max' => 255],
                    'tag' => ['label' => 'タグで絞り込む(タグ名)', 'type' => 'string', 'default' => '', 'max' => 100],
                    'layout' => ['label' => '表示のしかた', 'type' => 'enum', 'default' => 'card', 'options' => ['card', 'list']],
                    'columns' => ['label' => 'カードの列数(デスクトップ)', 'type' => 'int', 'default' => 3, 'min' => 1, 'max' => 4],
                    'showExcerpt' => ['label' => '本文の書き出しを表示する', 'type' => 'bool', 'default' => true],
                    'showDate' => ['label' => '公開日を表示する', 'type' => 'bool', 'default' => true],
                ],
                'styles' => [...self::MARGIN_STYLES],
            ],
            // ナビゲーション: 保存するのは項目の出どころと見た目だけで、公開側に返すときに項目を入れる(BlockDataResolver)
            'navigation' => [
                'label' => 'ナビゲーション',
                'category' => 'cms',
                'icon' => 'menu-button-wide',
                'children' => [],
                'props' => [
                    // site: サイトのナビメニュー(レイアウト管理と同じ項目)、pages: 固定ページ(リンクリストに表示するもの)
                    'source' => ['label' => 'メニューの項目', 'type' => 'enum', 'default' => 'site', 'options' => ['site', 'pages']],
                    'direction' => ['label' => '並べ方', 'type' => 'enum', 'default' => 'horizontal', 'options' => ['horizontal', 'vertical']],
                    'variant' => ['label' => '見た目', 'type' => 'enum', 'default' => 'links', 'options' => ['links', 'pills', 'underline']],
                    'align' => ['label' => '揃え', 'type' => 'enum', 'default' => 'start', 'options' => ['start', 'center', 'end']],
                ],
                'styles' => [...self::MARGIN_STYLES, 'fontSize'],
            ],
            // パンくず: 保存するのは見た目だけで、公開側は表示しているページのパンくず(パス解決 API の breadcrumbs)を並べる
            'breadcrumb' => [
                'label' => 'パンくず',
                'category' => 'cms',
                'icon' => 'chevron-double-right',
                'children' => [],
                'props' => [
                    'separator' => ['label' => '区切り', 'type' => 'enum', 'default' => 'slash', 'options' => ['slash', 'chevron', 'arrow']],
                    'showCurrent' => ['label' => '今のページを表示する', 'type' => 'bool', 'default' => true],
                    'align' => ['label' => '揃え', 'type' => 'enum', 'default' => 'start', 'options' => ['start', 'center', 'end']],
                ],
                'styles' => [...self::MARGIN_STYLES, 'fontSize'],
            ],
            // ギャラリー: 保存するのは取得の条件と見た目だけで、公開側に返すときに条件どおりの公開中の画像を入れる(BlockDataResolver)
            'gallery' => [
                'label' => 'ギャラリー',
                'category' => 'cms',
                'icon' => 'images',
                'children' => [],
                'props' => [
                    // 分類の id(null はすべて)。エディタは登録済みの分類から選ぶ
                    'category' => ['label' => '分類で絞り込む', 'type' => 'int', 'default' => null, 'min' => 1, 'max' => 2147483647, 'nullable' => true, 'source' => 'gallery-categories'],
                    'limit' => ['label' => '表示件数', 'type' => 'int', 'default' => 8, 'min' => 1, 'max' => 48],
                    'columns' => ['label' => 'タイルの列数(デスクトップ)', 'type' => 'int', 'default' => 4, 'min' => 2, 'max' => 6],
                    'showCaption' => ['label' => '名前を表示する', 'type' => 'bool', 'default' => true],
                ],
                'styles' => self::MARGIN_STYLES,
            ],
            // グローバルコンポーネント: 保存するのは参照するコンポーネントの id だけで、公開側に返すときにコンポーネントの公開中の内容を入れる
            // (BlockDataResolver)。ページの直下にだけ置け、コンポーネントの中には置けない
            'global' => [
                'label' => 'グローバルコンポーネント',
                'category' => 'cms',
                'icon' => 'puzzle',
                'children' => [],
                'props' => [
                    'component' => ['label' => '使うコンポーネント', 'type' => 'int', 'default' => null, 'min' => 1, 'max' => 2147483647, 'nullable' => true, 'source' => 'global-components'],
                ],
                'styles' => [],
            ],
            // 独自コンポーネント: 参照する部品の id と、部品の差し替えられる項目に入れた値(values。キーは「ノードの ID.項目名」)だけを保存し、
            // 公開側に返すときに部品の公開中の内容に値を当てはめて data.children に入れる(BlockDataResolver)。パレットには部品ごとに出す
            'custom' => [
                'label' => '独自コンポーネント',
                'category' => 'cms',
                'icon' => 'boxes',
                'children' => [],
                'props' => [
                    'component' => ['label' => '使う部品', 'type' => 'int', 'default' => null, 'min' => 1, 'max' => 2147483647, 'nullable' => true, 'source' => 'custom-components'],
                    'values' => ['label' => '差し替える項目', 'type' => 'overrides', 'default' => []],
                ],
                'styles' => self::MARGIN_STYLES,
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
     * 親(null は一番外側)の中に、指定した種類のブロックを置けるか(一番外側に置けるもの・使えるブロックは文脈で変わる)。
     */
    public static function allowsChild(?string $parentType, string $childType, BuilderContext $context = BuilderContext::Page): bool
    {
        $children = $parentType === null ? $context->rootChildren() : (self::get($parentType)['children'] ?? []);

        return $context->allowsBlock($childType) && in_array($childType, $children, true);
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
     * 管理画面のエディタに渡す定義(表示名は現在の言語に翻訳し、置ける親を足す)。文脈で使えないブロック・置ける場所のないブロックは渡さない。
     *
     * @return array{rootChildren: list<string>, blocks: array<string, array<string, mixed>>, styles: array<string, string|list<string>>}
     */
    public static function toArray(BuilderContext $context = BuilderContext::Page): array
    {
        $definitions = array_filter(self::definitions(), fn (string $type) => $context->allowsBlock($type), ARRAY_FILTER_USE_KEY);
        $blocks = [];

        foreach ($definitions as $type => $definition) {
            $parents = array_keys(array_filter($definitions, fn (array $parent) => in_array($type, $parent['children'], true)));

            if (in_array($type, $context->rootChildren(), true)) {
                array_unshift($parents, null);
            }

            // 文脈の中で置ける場所のないブロック(独自コンポーネントの中のセクションなど)は、パレットに出さないよう渡さない
            if ($parents === []) {
                continue;
            }

            $blocks[$type] = [
                ...$definition,
                'label' => __($definition['label']),
                'props' => array_map(fn (array $prop) => [...$prop, 'label' => __($prop['label'])], $definition['props']),
                'allowedParents' => $parents,
            ];
        }

        return [
            'rootChildren' => $context->rootChildren(),
            'blocks' => $blocks,
            'styles' => StyleRegistry::kinds(),
        ];
    }
}
