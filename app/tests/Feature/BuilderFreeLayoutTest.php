<?php

namespace Tests\Feature;

use App\Enums\BuilderContext;
use App\Models\PageBuilder;
use App\Models\PageBuilderComponent;
use App\Models\SinglePage;
use App\Support\Builder\BlockDataResolver;
use App\Support\Builder\BlockRegistry;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\BuilderPresenter;
use App\Support\Builder\BuilderValidator;
use App\Support\Builder\SchemaMigrator;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 自由配置(内容の v2): ブロックを座標(layout)で置く内容の検証・整形・公開側に返す形。
 */
class BuilderFreeLayoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private static function v2(array $children): array
    {
        return ['version' => SchemaMigrator::FREE_LAYOUT_VERSION, 'children' => $children];
    }

    /**
     * @return array<string, array<string, int|float>>
     */
    private static function at(int|float $x, int $y, int|float $w, ?int $h = null): array
    {
        return ['desktop' => ['x' => $x, 'y' => $y, 'w' => $w, ...($h === null ? [] : ['h' => $h])]];
    }

    /**
     * セクションの中に見出し・画像と、ボックスの中のボックスの中のテキストを置いた内容。
     *
     * @return array<string, mixed>
     */
    private static function content(): array
    {
        return self::v2([
            BuilderContent::node('section', styles: ['minHeight' => '480px', 'marginTop' => '24px'], children: [
                BuilderContent::node('heading', ['text' => 'タイトル'], ['fontSize' => '40px'], layout: [
                    'desktop' => ['x' => 8.33, 'y' => 96, 'w' => 50],
                    'mobile' => ['x' => 5, 'y' => 40, 'w' => 90],
                ]),
                BuilderContent::node('image', ['src' => 'image/builder/a.png'], layout: [
                    'desktop' => ['x' => 62, 'y' => 64, 'w' => 30, 'h' => 320],
                    'mobile' => ['x' => 5, 'y' => 160, 'w' => 90, 'h' => 200],
                ]),
                BuilderContent::node('box', styles: ['backgroundColor' => 'theme:light', 'borderRadius' => '8px'], layout: [
                    'desktop' => ['x' => 0, 'y' => 420, 'w' => 100, 'h' => 200],
                    'mobile' => ['x' => 0, 'y' => 400, 'w' => 100],
                ], children: [
                    BuilderContent::node('box', layout: self::at(10, 20, 80), children: [
                        BuilderContent::node('text', layout: self::at(0, 0, 100)),
                    ]),
                ]),
            ]),
        ]);
    }

    public function test_accepts_free_layout_content_and_keeps_its_version(): void
    {
        $validator = new BuilderValidator;
        $content = self::content();

        $this->assertSame([], $validator->errors($content));

        $normalized = $validator->normalize($content);
        $this->assertSame(SchemaMigrator::FREE_LAYOUT_VERSION, $normalized['version']);
        $this->assertSame(['x' => 8.33, 'y' => 96, 'w' => 50], $normalized['children'][0]['children'][0]['layout']['desktop']);

        // v1 の内容は v1 のまま受け付ける
        $this->assertSame(1, $validator->normalize(BuilderContent::empty())['version']);
    }

    public function test_normalize_orders_devices_and_values_and_trims_numbers(): void
    {
        $content = self::v2([BuilderContent::node('section', children: [
            BuilderContent::node('image', layout: [
                'mobile' => ['w' => 90.0, 'h' => 200, 'y' => 0, 'x' => 5],
                'desktop' => ['y' => 10, 'x' => 12.5, 'w' => 50.0],
            ]),
        ])]);

        $this->assertSame([], (new BuilderValidator)->errors($content));
        $this->assertSame([
            'desktop' => ['x' => 12.5, 'y' => 10, 'w' => 50],
            'mobile' => ['x' => 5, 'y' => 0, 'w' => 90, 'h' => 200],
        ], (new BuilderValidator)->normalize($content)['children'][0]['children'][0]['layout']);
    }

    /**
     * @return array<string, array{Closure(): array<string, mixed>, string}>
     */
    public static function invalidContents(): array
    {
        $section = fn (array ...$children) => self::v2([BuilderContent::node('section', children: $children)]);
        $heading = fn (array $layout) => BuilderContent::node('heading', layout: $layout);

        return [
            'v2 にコンテナ' => [fn () => $section(BuilderContent::node('container')), '「コンテナ」は「セクション」の中に置けません。'],
            'v2 にスペーサー' => [fn () => $section(BuilderContent::node('spacer', layout: self::at(0, 0, 100))), '「スペーサー」は「セクション」の中に置けません。'],
            'v1 にボックス' => [fn () => ['version' => 1, 'children' => [BuilderContent::node('section', children: [BuilderContent::node('box')])]], '「ボックス」は「セクション」の中に置けません。'],
            '位置がない' => [fn () => $section(BuilderContent::node('heading')), '「見出し」の位置がありません。'],
            'デスクトップの位置がない' => [fn () => $section($heading(['mobile' => ['x' => 0, 'y' => 0, 'w' => 100]])), '「見出し」のデスクトップの位置がありません。'],
            '知らない端末' => [fn () => $section($heading([...self::at(0, 0, 100), 'watch' => ['x' => 0, 'y' => 0, 'w' => 100]])), '端末「watch」の設定はありません。'],
            '右にはみ出す' => [fn () => $section($heading(self::at(60, 0, 50))), '「見出し」の位置(desktop)の値が正しくありません。'],
            '小数 3 桁' => [fn () => $section($heading(self::at(10.125, 0, 50))), '「見出し」の位置(desktop)の値が正しくありません。'],
            '幅が 0' => [fn () => $section($heading(self::at(0, 0, 0))), '「見出し」の位置(desktop)の値が正しくありません。'],
            'y が小数' => [fn () => $section($heading(['desktop' => ['x' => 0, 'y' => 1.5, 'w' => 50]])), '「見出し」の位置(desktop)の値が正しくありません。'],
            'y が文字' => [fn () => $section($heading(['desktop' => ['x' => 0, 'y' => '10', 'w' => 50]])), '「見出し」の位置(desktop)の値が正しくありません。'],
            '見出しに高さ' => [fn () => $section($heading(self::at(0, 0, 50, 100))), '「見出し」の位置(desktop)の値が正しくありません。'],
            '知らない値' => [fn () => $section($heading(['desktop' => ['x' => 0, 'y' => 0, 'w' => 50, 'z' => 1]])), '「見出し」の位置(desktop)の値が正しくありません。'],
            'スマホの位置がそろっていない' => [fn () => $section(
                $heading([...self::at(0, 0, 50), 'mobile' => ['x' => 0, 'y' => 0, 'w' => 100]]),
                $heading(self::at(50, 0, 50)),
            ), '端末「mobile」の位置は、同じ並びのブロックのすべてに入れるか、どれにも入れないでください。'],
            '自由配置の中で外側の余白' => [fn () => $section(BuilderContent::node('heading', styles: ['marginTop' => '8px'], layout: self::at(0, 0, 100))), '「見出し」に「marginTop」というスタイルは使えません。'],
            '自由配置の中で端末の幅' => [fn () => $section(BuilderContent::node('image', responsive: ['mobile' => ['width' => '50%']], layout: self::at(0, 0, 100))), '「画像」に「width」というスタイルは使えません。'],
            'セクションに位置' => [fn () => self::v2([BuilderContent::node('section', layout: self::at(0, 0, 100))]), '「セクション」は位置を持てません(自由配置の中のブロックだけが持てます)。'],
            'スライドに位置' => [fn () => $section(BuilderContent::node('slider', layout: self::at(0, 0, 100), children: [BuilderContent::node('slide', layout: self::at(0, 0, 100))])), '「スライド」は位置を持てません(自由配置の中のブロックだけが持てます)。'],
            'v1 で位置' => [fn () => ['version' => 1, 'children' => [BuilderContent::node('section', children: [BuilderContent::node('heading', layout: self::at(0, 0, 100))])]], '「見出し」は位置を持てません(自由配置の中のブロックだけが持てます)。'],
        ];
    }

    /**
     * @param  Closure(): array<string, mixed>  $content
     */
    #[DataProvider('invalidContents')]
    public function test_rejects_invalid_free_layout_content(Closure $content, string $message): void
    {
        $this->assertContains($message, array_column((new BuilderValidator)->errors($content()), 'message'));
    }

    public function test_custom_component_root_is_a_free_surface_in_v2(): void
    {
        $validator = new BuilderValidator;

        $this->assertSame([], $validator->errors(self::v2([
            BuilderContent::node('heading', layout: self::at(0, 0, 100)),
            BuilderContent::node('box', layout: self::at(0, 80, 100, 120)),
        ]), BuilderContext::CustomComponent));
        $this->assertContains('「見出し」の位置がありません。', array_column($validator->errors(self::v2([BuilderContent::node('heading')]), BuilderContext::CustomComponent), 'message'));
        $this->assertContains('「行」はページの直下に置けません。', array_column($validator->errors(self::v2([BuilderContent::node('row')]), BuilderContext::CustomComponent), 'message'));

        // v1 の独自コンポーネントは今までどおり(位置を持たない)
        $this->assertSame([], $validator->errors(['version' => 1, 'children' => [BuilderContent::node('heading')]], BuilderContext::CustomComponent));
    }

    public function test_registry_for_the_editor_offers_only_the_blocks_of_the_version(): void
    {
        $v1 = BlockRegistry::toArray();
        $this->assertArrayHasKey('row', $v1['blocks']);
        $this->assertArrayNotHasKey('box', $v1['blocks']);
        $this->assertNotContains('box', $v1['blocks']['section']['children']);

        $v2 = BlockRegistry::toArray(version: SchemaMigrator::FREE_LAYOUT_VERSION);
        foreach (BlockRegistry::LEGACY_BLOCKS as $type) {
            $this->assertArrayNotHasKey($type, $v2['blocks']);
        }
        $this->assertContains('box', $v2['blocks']['section']['children']);
        $this->assertNotContains('row', $v2['blocks']['section']['children']);
        $this->assertSame(['section', 'box'], $v2['blocks']['box']['allowedParents']);
        $this->assertTrue($v2['blocks']['image']['layoutHeight']);

        $custom = BlockRegistry::toArray(BuilderContext::CustomComponent, SchemaMigrator::FREE_LAYOUT_VERSION);
        $this->assertContains('box', $custom['rootChildren']);
        $this->assertNotContains('container', $custom['rootChildren']);
    }

    public function test_public_content_keeps_the_layout_as_objects(): void
    {
        $content = (new BuilderValidator)->normalize(self::content());
        $presented = BuilderPresenter::forPublic($content);

        $this->assertSame(SchemaMigrator::FREE_LAYOUT_VERSION, $presented['version']);
        $this->assertEquals((object) [
            'desktop' => (object) ['x' => 62, 'y' => 64, 'w' => 30, 'h' => 320],
            'mobile' => (object) ['x' => 5, 'y' => 160, 'w' => 90, 'h' => 200],
        ], $presented['children'][0]['children'][1]['layout']);
        $this->assertArrayNotHasKey('layout', $presented['children'][0]);
    }

    public function test_components_tell_the_version_of_their_published_content(): void
    {
        $v1 = PageBuilderComponent::factory()->published()->create();
        $v2 = PageBuilderComponent::factory()->create(['draft_content' => self::v2([BuilderContent::node('section')])]);
        $v2->publish();
        $v2->save();
        $unpublished = PageBuilderComponent::factory()->create();

        $this->assertSame(1, BlockDataResolver::dataFor('global', ['component' => $v1->id])['version']);
        $this->assertSame(2, BlockDataResolver::dataFor('global', ['component' => $v2->id])['version']);
        $this->assertNull(BlockDataResolver::dataFor('global', ['component' => $unpublished->id])['version']);
    }

    public function test_saving_v2_content_records_its_schema_version(): void
    {
        $this->actingAsAdmin();
        $singlePage = SinglePage::factory()->create();

        $this->putJson(route('admin.json.builder.single-pages.update', $singlePage), ['content' => self::content(), 'updated_at' => null])
            ->assertOk()
            ->assertJsonPath('content.version', 2)
            ->assertJsonPath('content.children.0.children.0.layout.desktop.x', 8.33);

        $builder = PageBuilder::query()->sole();
        $this->assertSame(2, $builder->schema_version);

        $this->postJson(route('admin.json.builder.single-pages.publish', $singlePage), ['updated_at' => $builder->updated_at->toIso8601String()])->assertOk();
        $this->assertSame(2, $builder->fresh()->published_content['version']);
    }

    public function test_editors_receive_the_registry_of_each_version(): void
    {
        $this->actingAsAdmin();
        $component = PageBuilderComponent::factory()->custom()->create();

        $this->getJson(route('admin.json.builder.top.show'))
            ->assertOk()
            ->assertJsonPath('registries.1.blocks.row.label', '行')
            ->assertJsonMissingPath('registries.1.blocks.box')
            ->assertJsonPath('registries.2.blocks.box.label', 'ボックス')
            ->assertJsonMissingPath('registries.2.blocks.row')
            ->assertJsonPath('registries.2.blocks.image.layoutHeight', true);

        $this->getJson(route('admin.json.builder.components.show', $component))
            ->assertOk()
            ->assertJsonPath('registries.2.rootChildren', fn (array $root) => in_array('box', $root, true) && ! in_array('container', $root, true));
    }
}
