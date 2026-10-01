<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\PageBuilder;
use App\Models\PageBuilderComponent;
use App\Models\SinglePage;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\BuilderValidator;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalComponentTest extends TestCase
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
     * グローバルコンポーネントのブロックを置いた、公開済みの固定ページ(/with-component)。
     */
    private function pageUsing(PageBuilderComponent $component): SinglePage
    {
        $singlePage = SinglePage::factory()->create(['slug' => 'with-component', 'title' => '会社概要', 'use_builder' => true]);
        PageBuilder::factory()->published()->create([
            'single_page_id' => $singlePage->id,
            'draft_content' => $this->content([
                BuilderContent::node('section', children: [BuilderContent::node('heading', ['text' => 'ページの見出し'])]),
                BuilderContent::node('global', ['component' => $component->id]),
            ]),
        ]);

        return $singlePage;
    }

    public function test_global_block_can_be_placed_only_at_the_root_of_a_page(): void
    {
        $validator = new BuilderValidator;

        $this->assertSame([], $validator->errors($this->content([BuilderContent::node('global', ['component' => 1])])));
        $this->assertNotEmpty($validator->errors($this->content([BuilderContent::node('section', children: [BuilderContent::node('global')])])));
        // コンポーネントの内容には置けない
        $this->assertNotEmpty($validator->errors($this->content([BuilderContent::node('global')]), allowsGlobal: false));
    }

    public function test_public_page_shows_the_published_content_of_the_component(): void
    {
        $component = PageBuilderComponent::factory()->published()->create([
            'draft_content' => $this->content([BuilderContent::node('section', children: [BuilderContent::node('heading', ['text' => '共通の案内'])])]),
        ]);
        $this->pageUsing($component);
        // 公開後の未公開の変更は出さない
        $draft = $component->draft_content;
        $draft['children'][0]['children'][0]['props']['text'] = '公開前の変更';
        $component->update(['draft_content' => $draft]);

        $response = $this->getJson(route('api.resolve', ['path' => '/with-component']))->assertOk();

        $response->assertJsonPath('data.builder.children.1.type', 'global')
            ->assertJsonPath('data.builder.children.1.data.children.0.type', 'section')
            ->assertJsonPath('data.builder.children.1.data.children.0.children.0.props.text', '共通の案内');

        // コンポーネントを公開し直すと、使っているページに反映される
        $component->publish();
        $component->save();
        $this->getJson(route('api.resolve', ['path' => '/with-component']))
            ->assertJsonPath('data.builder.children.1.data.children.0.children.0.props.text', '公開前の変更');
    }

    public function test_unpublished_or_deleted_components_show_nothing(): void
    {
        $component = PageBuilderComponent::factory()->create();
        $this->pageUsing($component);

        $this->getJson(route('api.resolve', ['path' => '/with-component']))->assertJsonPath('data.builder.children.1.data.children', []);

        $component->publish();
        $component->save();
        $component->delete();

        $this->getJson(route('api.resolve', ['path' => '/with-component']))->assertJsonPath('data.builder.children.1.data.children', []);
    }

    public function test_admin_can_create_rename_and_delete_components(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.builder-components.store'), ['name' => 'お問い合わせへの案内', 'description' => '']);

        $component = PageBuilderComponent::query()->sole();
        $response->assertRedirect(route('admin.builder.components', $component));
        $this->assertSame(BuilderContent::empty(), $component->draft_content);
        $this->assertFalse($component->isPublished());

        $this->get(route('admin.builder-components.index'))->assertOk()->assertSee('お問い合わせへの案内')->assertSee('0 ページ');

        $this->put(route('admin.builder-components.update', $component), ['name' => 'CTA', 'description' => '共通の案内'])
            ->assertRedirect(route('admin.builder-components.index'));
        $this->assertSame('CTA', $component->fresh()->name);

        $this->delete(route('admin.builder-components.destroy', $component))->assertRedirect(route('admin.builder-components.index'));
        $this->assertSoftDeleted($component);

        $this->assertSame([AuditAction::Created, AuditAction::Updated, AuditAction::Deleted], AuditLog::query()->orderBy('id')->pluck('action')->all());
    }

    public function test_components_used_by_pages_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();
        $component = PageBuilderComponent::factory()->published()->create(['name' => 'CTA']);
        $this->pageUsing($component);

        $this->get(route('admin.builder-components.index'))->assertSee('1 ページ');

        $this->delete(route('admin.builder-components.destroy', $component))
            ->assertRedirect(route('admin.builder-components.index'))
            ->assertSessionHas('error', '「CTA」は次のページで使っているため削除できません: 会社概要');

        $this->assertNotSoftDeleted($component);
    }

    public function test_component_editor_has_no_preview_and_no_nested_components(): void
    {
        $this->actingAsAdmin();
        $component = PageBuilderComponent::factory()->create(['name' => 'CTA']);

        $html = $this->get(route('admin.builder.components', $component))->assertOk()->getContent();
        preg_match('/id="page-builder" data-config="([^"]*)"/', $html, $matches);
        $config = json_decode(html_entity_decode($matches[1]), true);
        $this->assertNull($config['endpoints']['previewUrl']);
        $this->assertSame(route('admin.json.builder.components.update', $component), $config['endpoints']['update']);

        $this->getJson(route('admin.json.builder.components.show', $component))
            ->assertOk()
            ->assertJsonPath('page.type', 'component')
            ->assertJsonPath('page.title', 'CTA')
            ->assertJsonPath('registry.rootChildren', ['section'])
            ->assertJsonMissingPath('registry.blocks.global');

        $nested = BuilderContent::node('global', ['component' => $component->id]);
        $this->putJson(route('admin.json.builder.components.update', $component), [
            'content' => $this->content([$nested]),
            'updated_at' => $component->updated_at->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['nodes.'.$nested['id']]);
    }

    public function test_component_editor_saves_publishes_and_discards(): void
    {
        $this->actingAsAdmin();
        $component = PageBuilderComponent::factory()->create();
        $content = $this->content([BuilderContent::node('section', children: [BuilderContent::node('heading', ['text' => '新しい案内'])])]);

        $updatedAt = $this->putJson(route('admin.json.builder.components.update', $component), [
            'content' => $content,
            'updated_at' => $component->updated_at->toIso8601String(),
        ])->assertOk()->json('updated_at');

        $this->postJson(route('admin.json.builder.components.publish', $component), ['updated_at' => $updatedAt])
            ->assertOk()
            ->assertJsonPath('published', true);
        $this->assertSame('新しい案内', $component->fresh()->published_content['children'][0]['children'][0]['props']['text']);

        $draft = $component->fresh()->draft_content;
        $draft['children'] = [];
        $component->fresh()->update(['draft_content' => $draft]);

        $this->postJson(route('admin.json.builder.components.discard', $component), ['updated_at' => $component->fresh()->updated_at->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('has_unpublished_changes', false);

        $this->assertSame(
            [AuditAction::Updated, AuditAction::Published, AuditAction::DraftDiscarded],
            AuditLog::query()->where('subject_type', 'page_builder_component')->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_page_editor_lists_components_with_their_published_content(): void
    {
        $this->actingAsAdmin();
        PageBuilderComponent::factory()->published()->create(['name' => 'B']);
        PageBuilderComponent::factory()->create(['name' => 'A']);

        $this->getJson(route('admin.json.builder-components.index'))
            ->assertOk()
            ->assertJsonPath('0.name', 'A')
            ->assertJsonPath('0.published', false)
            ->assertJsonPath('0.content', null)
            ->assertJsonPath('1.name', 'B')
            ->assertJsonPath('1.content.children.0.type', 'section');
    }
}
