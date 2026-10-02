<?php

namespace Tests\Feature;

use App\Enums\BuilderContext;
use App\Models\PageBuilder;
use App\Models\PageBuilderComponent;
use App\Models\SinglePage;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\BuilderPresenter;
use App\Support\Builder\BuilderValidator;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomComponentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<array<string, mixed>>  $children
     * @return array{version: int, children: list<array<string, mixed>>}
     */
    private function content(array $children): array
    {
        return ['version' => SchemaMigrator::CURRENT_VERSION, 'children' => $children];
    }

    /**
     * 部品のノードの ID(差し替えた値のキーに使う)。
     *
     * @return array{image: string, heading: string, text: string}
     */
    private function ids(PageBuilderComponent $component): array
    {
        [$image, $heading, $text] = $component->published_content['children'];

        return ['image' => $image['id'], 'heading' => $heading['id'], 'text' => $text['id']];
    }

    /**
     * 独自コンポーネントのブロックを置いた内容を、公開側の形にしたときの部品の中身のノード。
     *
     * @param  array<string, mixed>  $values
     * @return list<array<string, mixed>>
     */
    private function renderedChildren(PageBuilderComponent $component, array $values): array
    {
        $public = BuilderPresenter::forPublic($this->content([
            BuilderContent::node('section', children: [BuilderContent::node('custom', ['component' => $component->id, 'values' => $values])]),
        ]));

        return json_decode(json_encode($public['children'][0]['children'][0]['data']['children']), true);
    }

    public function test_admin_can_create_a_custom_component_and_open_its_editor(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.builder-components.store'), ['kind' => 'custom', 'name' => 'カード', 'description' => '']);

        $component = PageBuilderComponent::query()->sole();
        $response->assertRedirect(route('admin.builder.components', $component));
        $this->assertSame('custom', $component->kind->value);

        $this->getJson(route('admin.json.builder.components.show', $component))
            ->assertOk()
            ->assertJsonPath('page.kind', 'custom')
            ->assertJsonPath('registry.rootChildren.0', 'container')
            ->assertJsonMissingPath('registry.blocks.global')
            ->assertJsonMissingPath('registry.blocks.custom')
            ->assertJsonMissingPath('registry.blocks.section')
            ->assertJsonPath('registry.blocks.container.allowedParents.0', null);

        // 種類はあとから変えない
        $this->put(route('admin.builder-components.update', $component), ['kind' => 'global', 'name' => 'カード 2'])->assertRedirect();
        $this->assertSame('custom', $component->fresh()->kind->value);
        $this->assertSame('カード 2', $component->fresh()->name);
    }

    public function test_custom_component_content_is_validated_in_its_own_context(): void
    {
        $validator = new BuilderValidator;
        $heading = BuilderContent::node('heading');
        $heading['exposed'] = ['text' => '見出し'];

        $this->assertSame([], $validator->errors($this->content([$heading, BuilderContent::node('row', children: [BuilderContent::node('column')])]), BuilderContext::CustomComponent));
        $this->assertNotEmpty($validator->errors($this->content([BuilderContent::node('section')]), BuilderContext::CustomComponent));
        $this->assertNotEmpty($validator->errors($this->content([BuilderContent::node('custom')]), BuilderContext::CustomComponent));
        $this->assertNotEmpty($validator->errors($this->content([BuilderContent::node('global')]), BuilderContext::CustomComponent));

        // 差し替えられる項目は独自コンポーネントの中身だけが持て、選択肢をデータから作る項目・長すぎる名前は使えない
        $this->assertNotEmpty($validator->errors($this->content([BuilderContent::node('section', children: [$heading])])));
        $gallery = BuilderContent::node('gallery');
        $gallery['exposed'] = ['category' => '分類'];
        $this->assertNotEmpty($validator->errors($this->content([$gallery]), BuilderContext::CustomComponent));
        $heading['exposed'] = ['text' => str_repeat('あ', 51)];
        $this->assertNotEmpty($validator->errors($this->content([$heading]), BuilderContext::CustomComponent));
    }

    public function test_custom_block_values_must_be_flat_and_keyed_by_node_and_prop(): void
    {
        $validator = new BuilderValidator;
        $valid = BuilderContent::newId('heading').'.text';
        $block = fn (array $values) => $this->content([BuilderContent::node('section', children: [BuilderContent::node('custom', ['component' => 1, 'values' => $values])])]);

        $this->assertSame([], $validator->errors($block([$valid => '差し替えた見出し', BuilderContent::newId('image').'.src' => null])));
        $this->assertNotEmpty($validator->errors($block(['text' => 'キーの形が違う'])));
        $this->assertNotEmpty($validator->errors($block([$valid => ['入れ子']])));
    }

    public function test_public_content_applies_valid_values_only_to_exposed_props(): void
    {
        $component = PageBuilderComponent::factory()->custom()->published()->create();
        $ids = $this->ids($component);

        $children = $this->renderedChildren($component, [
            $ids['heading'].'.text' => '差し替えた見出し',
            $ids['heading'].'.level' => 1,
            $ids['text'].'.html' => '<p onclick="alert(1)">差し替えた本文</p><script>alert(1)</script>',
            $ids['image'].'.src' => 'image/builder/photo.png',
        ]);

        $this->assertSame(Storage::disk('public')->url('image/builder/photo.png'), $children[0]['props']['src']);
        $this->assertSame('差し替えた見出し', $children[1]['props']['text']);
        // 差し替えられる項目にしていない項目(見出しのレベル)は部品の値のまま
        $this->assertSame(3, $children[1]['props']['level']);
        $this->assertSame('<p>差し替えた本文</p>', $children[2]['props']['html']);
        $this->assertArrayNotHasKey('exposed', $children[1]);

        // 空・項目に合わない値は部品の値のまま
        $children = $this->renderedChildren($component, [$ids['heading'].'.text' => '', $ids['image'].'.src' => '../secret.png']);
        $this->assertSame('カードの見出し', $children[1]['props']['text']);
        $this->assertNull($children[0]['props']['src']);
    }

    public function test_public_content_is_empty_for_unpublished_or_global_components(): void
    {
        $unpublished = PageBuilderComponent::factory()->custom()->create();
        $global = PageBuilderComponent::factory()->published()->create();

        $this->assertSame([], $this->renderedChildren($unpublished, []));
        $this->assertSame([], $this->renderedChildren($global, []));
    }

    public function test_resolve_api_renders_the_custom_block_on_a_page(): void
    {
        $component = PageBuilderComponent::factory()->custom()->published()->create();
        $ids = $this->ids($component);
        $singlePage = SinglePage::factory()->create(['slug' => 'cards', 'use_builder' => true]);
        PageBuilder::factory()->published()->create([
            'single_page_id' => $singlePage->id,
            'draft_content' => $this->content([BuilderContent::node('section', children: [
                BuilderContent::node('custom', ['component' => $component->id, 'values' => [$ids['heading'].'.text' => '1 枚目']]),
                BuilderContent::node('custom', ['component' => $component->id, 'values' => [$ids['heading'].'.text' => '2 枚目']]),
            ])]),
        ]);

        $this->getJson('/api/resolve?path=/cards')
            ->assertOk()
            ->assertJsonPath('data.builder.children.0.children.0.data.children.1.props.text', '1 枚目')
            ->assertJsonPath('data.builder.children.0.children.1.data.children.1.props.text', '2 枚目');
    }

    public function test_components_used_inside_other_components_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();
        $custom = PageBuilderComponent::factory()->custom()->published()->create(['name' => 'カード']);
        PageBuilderComponent::factory()->create([
            'name' => 'お知らせ',
            'draft_content' => $this->content([BuilderContent::node('section', children: [BuilderContent::node('custom', ['component' => $custom->id])])]),
        ]);

        $this->delete(route('admin.builder-components.destroy', $custom))
            ->assertSessionHas('error', '「カード」は次の場所で使っているため削除できません: コンポーネント「お知らせ」');
        $this->assertNotSoftDeleted($custom);
    }

    public function test_images_in_values_are_counted_as_used(): void
    {
        $paths = BuilderContent::imagePaths($this->content([BuilderContent::node('section', children: [
            BuilderContent::node('custom', ['component' => 1, 'values' => [BuilderContent::newId('image').'.src' => 'image/builder/card.png', BuilderContent::newId('heading').'.text' => '見出し']]),
        ])]));

        $this->assertSame(['image/builder/card.png'], $paths);
    }

    public function test_transfer_links_custom_blocks_by_name_and_restores_value_images(): void
    {
        $this->actingAsAdmin();
        Storage::fake('public');
        Storage::disk('public')->put('image/builder/card.png', UploadedFile::fake()->image('card.png', 20, 20)->getContent());
        $component = PageBuilderComponent::factory()->custom()->published()->create(['name' => 'カード']);
        $key = $this->ids($component)['image'].'.src';
        $content = $this->content([BuilderContent::node('section', children: [BuilderContent::node('custom', ['component' => $component->id, 'values' => [$key => 'image/builder/card.png']])])]);
        $file = $this->postJson(route('admin.json.builder.export'), ['content' => $content])->assertOk()->json();
        Storage::disk('public')->delete('image/builder/card.png');

        $response = $this->post(route('admin.json.builder.import'), ['file' => UploadedFile::fake()->createWithContent('page.json', json_encode($file)), 'context' => 'page'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('images', 1)
            ->assertJsonPath('content.children.0.children.0.props.component', $component->id);
        $restored = $response->json("content.children.0.children.0.props.values.{$key}");
        $this->assertNotSame('image/builder/card.png', $restored);
        Storage::disk('public')->assertExists($restored);

        // 同じ名前の独自コンポーネントがなければブロックを外す
        $component->delete();
        $this->post(route('admin.json.builder.import'), ['file' => UploadedFile::fake()->createWithContent('page.json', json_encode($file))], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonCount(0, 'content.children.0.children')
            ->assertJsonPath('warnings.0', '独自コンポーネント「カード」が見つからないため、ブロックを外しました。');
    }

    public function test_importing_into_a_custom_component_keeps_node_ids(): void
    {
        $this->actingAsAdmin();
        $component = PageBuilderComponent::factory()->custom()->published()->create();
        $file = $this->postJson(route('admin.json.builder.export'), ['content' => $component->published_content])->assertUnprocessable();

        // 独自コンポーネントの中身はページの文脈では検証を通らないため、ファイルを直接作る
        $file = ['format' => 'biscuit-page-builder', 'formatVersion' => 1, 'content' => $component->published_content];

        $this->post(route('admin.json.builder.import'), ['file' => UploadedFile::fake()->createWithContent('card.json', json_encode($file)), 'context' => 'custom'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('content.children.1.id', $this->ids($component)['heading'])
            ->assertJsonPath('content.children.1.exposed.text', '見出し');
    }
}
