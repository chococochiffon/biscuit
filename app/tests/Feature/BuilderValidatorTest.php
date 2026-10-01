<?php

namespace Tests\Feature;

use App\Support\Builder\BlockRegistry;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\BuilderValidator;
use App\Support\Builder\SchemaMigrator;
use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BuilderValidatorTest extends TestCase
{
    /**
     * セクション > コンテナ > 行 > カラム > 見出し の内容。
     *
     * @return array<string, mixed>
     */
    private static function content(): array
    {
        return [
            'version' => SchemaMigrator::CURRENT_VERSION,
            'children' => [
                BuilderContent::node('section', styles: ['paddingTop' => '48px'], children: [
                    BuilderContent::node('container', children: [
                        BuilderContent::node('row', children: [
                            BuilderContent::node('column', ['span' => 6], children: [
                                BuilderContent::node('heading', ['text' => 'タイトル'], ['fontSize' => '32px'], responsive: ['mobile' => ['fontSize' => '22px']]),
                            ]),
                        ]),
                    ]),
                ]),
            ],
        ];
    }

    public function test_accepts_valid_content(): void
    {
        $this->assertSame([], (new BuilderValidator)->errors(self::content()));
        $this->assertSame([], (new BuilderValidator)->errors(BuilderContent::empty()));
    }

    public function test_every_block_with_default_props_is_valid_where_it_can_be_placed(): void
    {
        $leaves = array_map(fn (string $type) => BuilderContent::node($type), ['heading', 'text', 'image', 'button', 'spacer', 'divider', 'video', 'article-list', 'navigation', 'breadcrumb', 'gallery']);
        $content = [
            'version' => SchemaMigrator::CURRENT_VERSION,
            'children' => [
                BuilderContent::node('section', children: [
                    ...$leaves,
                    BuilderContent::node('container', children: [BuilderContent::node('row', children: [BuilderContent::node('column', children: $leaves)])]),
                ]),
            ],
        ];

        // 同じ ID を 2 回使わないよう、カラムの中は ID を振り直す
        $content['children'][0]['children'][count($leaves)]['children'][0]['children'][0]['children'] = array_map(
            fn (array $leaf) => [...$leaf, 'id' => BuilderContent::newId($leaf['type'])],
            $leaves,
        );

        $this->assertSame([], (new BuilderValidator)->errors($content));
    }

    /**
     * @return array<string, array{Closure(array<string, mixed>): mixed, string|null}>
     */
    public static function invalidContentProvider(): array
    {
        return [
            '内容がオブジェクトでない' => [fn () => 'not json', null],
            '未対応の版' => [fn (array $content) => [...$content, 'version' => 2], null],
            'ルートに知らないキー' => [fn (array $content) => [...$content, 'html' => '<p>x</p>'], null],
            '定義にない種類' => [function (array $content) {
                $content['children'][0]['type'] = 'script';

                return $content;
            }, null],
            'ページの直下に見出し' => [function (array $content) {
                $content['children'][] = BuilderContent::node('heading');

                return $content;
            }, 'heading'],
            '行の中に見出し' => [function (array $content) {
                $content['children'][0]['children'][0]['children'][0]['children'][] = BuilderContent::node('heading');

                return $content;
            }, 'heading'],
            '見出しの中に子' => [function (array $content) {
                $content['children'][0]['children'][0]['children'][0]['children'][0]['children'][0]['children'] = [BuilderContent::node('text')];

                return $content;
            }, 'heading'],
            'ID の形式が違う' => [function (array $content) {
                $content['children'][0]['id'] = 'section_1';

                return $content;
            }, null],
            'ID の種類が違う' => [function (array $content) {
                $content['children'][0]['id'] = BuilderContent::newId('heading');

                return $content;
            }, null],
            'ID が重複' => [function (array $content) {
                $duplicate = BuilderContent::node('section');
                $duplicate['id'] = $content['children'][0]['id'];
                $content['children'][] = $duplicate;

                return $content;
            }, 'section'],
            '定義にない props' => [function (array $content) {
                $content['children'][0]['props']['onclick'] = 'alert(1)';

                return $content;
            }, 'section'],
            '見出しのレベルが範囲外' => [function (array $content) {
                $content['children'][0]['children'][0]['children'][0]['children'][0]['children'][0]['props']['level'] = 7;

                return $content;
            }, 'heading'],
            '見出しの文字が長すぎる' => [function (array $content) {
                $content['children'][0]['children'][0]['children'][0]['children'][0]['children'][0]['props']['text'] = str_repeat('あ', 201);

                return $content;
            }, 'heading'],
            '真偽値の項目に文字' => [function (array $content) {
                $content['children'][0]['children'][] = BuilderContent::node('article-list', ['showDate' => 'yes']);

                return $content;
            }, 'article-list'],
            '記事一覧の件数が上限を超える' => [function (array $content) {
                $content['children'][0]['children'][] = BuilderContent::node('article-list', ['limit' => 21]);

                return $content;
            }, 'article-list'],
            '行の中に記事一覧' => [function (array $content) {
                $content['children'][0]['children'][0]['children'][0]['children'][] = BuilderContent::node('article-list');

                return $content;
            }, 'article-list'],
            'ボタンの target が選択肢にない' => [function (array $content) {
                $content['children'][0]['children'][] = BuilderContent::node('button', ['target' => '_parent']);

                return $content;
            }, 'button'],
            'javascript の URL' => [function (array $content) {
                $content['children'][0]['children'][] = BuilderContent::node('button', ['href' => 'javascript:alert(1)']);

                return $content;
            }, 'button'],
            'data の URL' => [function (array $content) {
                $content['children'][0]['children'][] = BuilderContent::node('button', ['href' => 'data:text/html,<script>alert(1)</script>']);

                return $content;
            }, 'button'],
            'プロトコル相対の URL' => [function (array $content) {
                $content['children'][0]['children'][] = BuilderContent::node('button', ['href' => '//evil.example.com']);

                return $content;
            }, 'button'],
            '外部の画像' => [function (array $content) {
                $content['children'][0]['children'][] = BuilderContent::node('image', ['src' => 'https://evil.example.com/a.png']);

                return $content;
            }, 'image'],
            'image の外へ出る画像のパス' => [function (array $content) {
                $content['children'][0]['children'][] = BuilderContent::node('image', ['src' => 'image/../../.env.png']);

                return $content;
            }, 'image'],
            'ブロックで使えないスタイル' => [function (array $content) {
                $content['children'][0]['children'][0]['children'][0]['styles']['fontSize'] = '16px';

                return $content;
            }, 'row'],
            '定義にないスタイル' => [function (array $content) {
                $content['children'][0]['styles']['behavior'] = 'url(x.htc)';

                return $content;
            }, 'section'],
            'CSS を差し込むスタイルの値' => [function (array $content) {
                $content['children'][0]['styles']['backgroundColor'] = 'red; background-image: url(https://evil.example.com)';

                return $content;
            }, 'section'],
            '単位のない長さ' => [function (array $content) {
                $content['children'][0]['styles']['paddingTop'] = '48';

                return $content;
            }, 'section'],
            '端末ごとのスタイルに知らない端末' => [function (array $content) {
                $content['children'][0]['responsive'] = ['watch' => ['paddingTop' => '8px']];

                return $content;
            }, 'section'],
            '端末ごとのスタイルの値が不正' => [function (array $content) {
                $content['children'][0]['responsive'] = ['mobile' => ['paddingTop' => 'expression(alert(1))']];

                return $content;
            }, 'section'],
            'ノードに知らないキー' => [function (array $content) {
                $content['children'][0]['html'] = '<script></script>';

                return $content;
            }, 'section'],
        ];
    }

    /**
     * @param  Closure(array<string, mixed>): mixed  $modify
     */
    #[DataProvider('invalidContentProvider')]
    public function test_rejects_invalid_content(Closure $modify, ?string $nodeType): void
    {
        $errors = (new BuilderValidator)->errors($modify(self::content()));

        $this->assertNotEmpty($errors);

        // ノードを特定できるエラーには、そのノードの ID が入る(エディタがそのノードを選択して知らせる)
        if ($nodeType !== null) {
            $this->assertStringStartsWith($nodeType.'_', (string) $errors[0]['node']);
        }
    }

    public function test_error_messages_name_the_block(): void
    {
        $content = self::content();
        $content['children'][0]['children'][0]['children'][0]['children'][] = BuilderContent::node('heading');

        $this->assertSame('「見出し」は「行」の中に置けません。', (new BuilderValidator)->errors($content)[0]['message']);
    }

    public function test_rejects_too_many_blocks(): void
    {
        config(['limits.builder_nodes' => 3]);
        $content = BuilderContent::empty();
        $content['children'][] = BuilderContent::node('section', children: array_map(fn () => BuilderContent::node('spacer'), range(1, 3)));

        $this->assertSame('ブロックの数が多すぎます(最大 3 個)。', (new BuilderValidator)->errors($content)[0]['message']);
    }

    public function test_normalize_fills_default_props_and_sanitizes_html(): void
    {
        $text = BuilderContent::node('text', ['html' => '<p onclick="alert(1)">本文<script>alert(1)</script></p>']);
        $button = BuilderContent::node('button');
        $button['props'] = ['text' => '送信'];
        $section = BuilderContent::node('section', children: [$text, $button], responsive: ['tablet' => [], 'mobile' => ['paddingTop' => '8px']]);
        $content = ['version' => SchemaMigrator::CURRENT_VERSION, 'children' => [$section]];

        $validator = new BuilderValidator;
        $this->assertSame([], $validator->errors($content));
        $normalized = $validator->normalize($content);

        $this->assertSame('<p>本文</p>', $normalized['children'][0]['children'][0]['props']['html']);
        $this->assertSame([...BlockRegistry::defaultProps('button'), 'text' => '送信'], $normalized['children'][0]['children'][1]['props']);
        // 空の端末の設定は捨てる
        $this->assertSame(['mobile' => ['paddingTop' => '8px']], $normalized['children'][0]['responsive']);
        $this->assertArrayNotHasKey('children', $normalized['children'][0]['children'][0]);
    }

    public function test_block_registry_exports_definitions_for_the_editor(): void
    {
        $registry = BlockRegistry::toArray();

        $this->assertSame(['section'], $registry['rootChildren']);
        $this->assertSame([null], $registry['blocks']['section']['allowedParents']);
        $this->assertSame(['section', 'container', 'column'], $registry['blocks']['heading']['allowedParents']);
        $this->assertSame(['row'], $registry['blocks']['column']['allowedParents']);
        $this->assertSame('見出し', $registry['blocks']['heading']['label']);
        $this->assertSame('length', $registry['styles']['paddingTop']);
        $this->assertSame('見出しのレベル', $registry['blocks']['heading']['props']['level']['label']);

        // 置ける子の定義は、定義済みのブロックだけを指す
        foreach (BlockRegistry::definitions() as $definition) {
            foreach ($definition['children'] as $child) {
                $this->assertTrue(BlockRegistry::has($child));
            }
        }
    }

    public function test_schema_migrator_keeps_current_version_and_rejects_unknown_versions(): void
    {
        $migrator = new SchemaMigrator;
        $content = self::content();

        $this->assertSame($content, $migrator->migrate($content));

        foreach ([0, SchemaMigrator::CURRENT_VERSION + 1, '1', null] as $version) {
            try {
                $migrator->migrate([...$content, 'version' => $version]);
                $this->fail('version '.json_encode($version).' should be rejected');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
