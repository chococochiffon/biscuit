<?php

namespace Tests\Feature;

use App\Models\PageBuilder;
use App\Models\PageBuilderComponent;
use App\Models\PageBuilderVersion;
use App\Models\SinglePage;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class PageBuilderVersionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * セクションの中に見出しを 1 つ置いた内容。
     *
     * @return array<string, mixed>
     */
    private function content(string $heading = '見出し'): array
    {
        return [
            'version' => SchemaMigrator::CURRENT_VERSION,
            'children' => [
                BuilderContent::node('section', children: [BuilderContent::node('heading', ['text' => $heading])]),
            ],
        ];
    }

    private function publishTop(PageBuilder $builder, string $heading): void
    {
        $builder->refresh()->update(['draft_content' => $this->content($heading)]);

        $this->postJson(route('admin.json.builder.top.publish'), ['updated_at' => $builder->updated_at->toIso8601String()])->assertOk();
    }

    public function test_publishing_records_the_published_content_as_a_version(): void
    {
        $admin = $this->actingAsAdmin();
        $builder = PageBuilder::factory()->top()->create();

        $this->publishTop($builder, '最初の公開');

        $version = $builder->versions()->sole();
        $this->assertSame('page_builder', $version->versionable_type);
        $this->assertSame($admin->id, $version->administrator_id);
        $this->assertSame(2, $version->node_count);
        $this->assertSame($builder->fresh()->published_content, $version->content);
    }

    public function test_publishing_a_global_component_records_a_version(): void
    {
        $this->actingAsAdmin();
        $component = PageBuilderComponent::factory()->create(['draft_content' => $this->content('お知らせ')]);

        $this->postJson(route('admin.json.builder.components.publish', $component), ['updated_at' => $component->updated_at->toIso8601String()])
            ->assertOk();

        $version = $component->versions()->sole();
        $this->assertSame('page_builder_component', $version->versionable_type);
        $this->assertSame('お知らせ', $version->content['children'][0]['children'][0]['props']['text']);
    }

    public function test_versions_are_listed_newest_first_with_the_current_one_marked(): void
    {
        $admin = $this->actingAsAdmin();
        $builder = PageBuilder::factory()->top()->create();
        Date::setTestNow('2026-10-01 10:00:00');
        $this->publishTop($builder, '1 回目');
        Date::setTestNow('2026-10-02 10:00:00');
        $this->publishTop($builder, '2 回目');
        // ほかのページの版は出さない
        PageBuilder::factory()->published()->create()->recordVersion(null);

        $this->getJson(route('admin.json.builder.top.versions.index'))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.published_at', '2026-10-02T10:00:00+09:00')
            ->assertJsonPath('0.administrator', $admin->name)
            ->assertJsonPath('0.node_count', 2)
            ->assertJsonPath('0.current', true)
            ->assertJsonPath('1.published_at', '2026-10-01T10:00:00+09:00')
            ->assertJsonPath('1.current', false);
    }

    public function test_versions_are_limited_to_the_configured_count(): void
    {
        $this->actingAsAdmin();
        config(['limits.builder_versions' => 2]);
        $builder = PageBuilder::factory()->top()->published()->create();
        $builder->recordVersion(null);
        $builder->recordVersion(null);
        $latest = $builder->recordVersion(null);

        $this->getJson(route('admin.json.builder.top.versions.index'))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $latest->id)
            ->assertJsonPath('0.administrator', null);
    }

    public function test_versions_are_empty_before_the_builder_exists(): void
    {
        $this->actingAsAdmin();
        $singlePage = SinglePage::factory()->create();

        $this->getJson(route('admin.json.builder.top.versions.index'))->assertOk()->assertExactJson([]);
        $this->getJson(route('admin.json.builder.single-pages.versions.index', $singlePage))->assertOk()->assertExactJson([]);
    }

    public function test_a_version_returns_its_content_for_the_editor(): void
    {
        $this->actingAsAdmin();
        $builder = PageBuilder::factory()->published()->create(['draft_content' => $this->content('公開した見出し')]);
        $version = $builder->recordVersion(null);
        $builder->update(['draft_content' => $this->content('編集中の見出し')]);

        $this->getJson(route('admin.json.builder.single-pages.versions.show', [$builder->singlePage, $version]))
            ->assertOk()
            ->assertJsonPath('id', $version->id)
            ->assertJsonPath('content.children.0.children.0.props.text', '公開した見出し');
    }

    public function test_a_version_of_another_target_is_not_found(): void
    {
        $this->actingAsAdmin();
        $top = PageBuilder::factory()->top()->published()->create();
        $topVersion = $top->recordVersion(null);
        $page = PageBuilder::factory()->published()->create();
        $pageVersion = $page->recordVersion(null);
        $component = PageBuilderComponent::factory()->create(['id' => $page->id, 'published_content' => $this->content()]);
        $componentVersion = $component->recordVersion(null);

        $this->getJson(route('admin.json.builder.top.versions.show', $pageVersion))->assertNotFound();
        $this->getJson(route('admin.json.builder.single-pages.versions.show', [$page->singlePage, $topVersion]))->assertNotFound();
        // id が同じでも種類の違う対象の版は返さない
        $this->getJson(route('admin.json.builder.components.versions.show', [$component, $pageVersion]))->assertNotFound();
        $this->getJson(route('admin.json.builder.single-pages.versions.show', [$page->singlePage, $componentVersion]))->assertNotFound();
        $this->getJson(route('admin.json.builder.components.versions.show', [$component, $componentVersion]))->assertOk();
    }

    public function test_soft_deleted_versions_are_not_listed_or_found(): void
    {
        $this->actingAsAdmin();
        $builder = PageBuilder::factory()->top()->published()->create();
        $version = $builder->recordVersion(null);
        $version->delete();

        $this->getJson(route('admin.json.builder.top.versions.index'))->assertOk()->assertExactJson([]);
        $this->getJson(route('admin.json.builder.top.versions.show', $version))->assertNotFound();
        $this->assertSoftDeleted($version);
        $this->assertSame(1, PageBuilderVersion::withTrashed()->count());
    }

    public function test_guests_cannot_see_versions(): void
    {
        $builder = PageBuilder::factory()->top()->published()->create();
        $version = $builder->recordVersion(null);

        $this->getJson(route('admin.json.builder.top.versions.index'))->assertUnauthorized();
        $this->getJson(route('admin.json.builder.top.versions.show', $version))->assertUnauthorized();
    }
}
