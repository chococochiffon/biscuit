<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\PageBuilder;
use App\Models\SinglePage;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageBuilderJsonControllerTest extends TestCase
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

    public function test_guests_cannot_use_the_builder_json(): void
    {
        $singlePage = SinglePage::factory()->create();

        $this->getJson(route('admin.json.builder.top.show'))->assertUnauthorized();
        $this->getJson(route('admin.json.builder.single-pages.show', $singlePage))->assertUnauthorized();
        $this->putJson(route('admin.json.builder.top.update'), ['content' => $this->content()])->assertUnauthorized();
        $this->assertSame(0, PageBuilder::query()->count());
    }

    public function test_show_returns_an_empty_draft_without_creating_the_builder(): void
    {
        $this->actingAsAdmin();
        $singlePage = SinglePage::factory()->create(['title' => '会社概要']);

        $this->getJson(route('admin.json.builder.single-pages.show', $singlePage))
            ->assertOk()
            ->assertJsonPath('page.type', 'single_page')
            ->assertJsonPath('page.id', $singlePage->id)
            ->assertJsonPath('page.title', '会社概要')
            ->assertJsonPath('page.use_builder', false)
            ->assertJsonPath('content', ['version' => 1, 'children' => []])
            ->assertJsonPath('published', false)
            ->assertJsonPath('updated_at', null)
            ->assertJsonPath('registry.rootChildren', ['section', 'global'])
            ->assertJsonPath('registry.blocks.heading.label', '見出し');

        $this->assertSame(0, PageBuilder::query()->count());
    }

    public function test_show_returns_empty_props_and_styles_as_json_objects(): void
    {
        $this->actingAsAdmin();
        PageBuilder::factory()->top()->create(['draft_content' => [
            'version' => SchemaMigrator::CURRENT_VERSION,
            'children' => [BuilderContent::node('section', children: [BuilderContent::node('divider')])],
        ]]);

        $json = $this->getJson(route('admin.json.builder.top.show'))->assertOk()->getContent();

        $this->assertStringContainsString('"type":"divider","props":{},"styles":{}', $json);
    }

    public function test_update_creates_the_builder_and_returns_the_saved_state(): void
    {
        $admin = $this->actingAsAdmin();
        $singlePage = SinglePage::factory()->create(['updated_at' => now()->subDay()]);

        $response = $this->putJson(route('admin.json.builder.single-pages.update', $singlePage), [
            'content' => $this->content(),
            'updated_at' => null,
        ]);

        $builder = $singlePage->builder()->sole();
        $response->assertOk()
            ->assertJsonPath('updated_at', $builder->updated_at->toIso8601String())
            ->assertJsonPath('has_unpublished_changes', true)
            ->assertJsonPath('content.children.0.children.0.props.text', '見出し');
        $this->assertSame('見出し', $builder->draft_content['children'][0]['children'][0]['props']['text']);
        $this->assertNull($builder->published_content);
        $this->assertTrue($singlePage->fresh()->updated_at->isToday());

        $log = AuditLog::query()->sole();
        $this->assertSame(AuditAction::Updated, $log->action);
        $this->assertSame('page_builder', $log->subject_type);
        $this->assertSame($singlePage->title, $log->subject_label);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame(['nodes' => 2], $log->metadata);
    }

    public function test_update_keeps_strings_as_sent_and_sanitizes_html(): void
    {
        $this->actingAsAdmin();
        $content = BuilderContent::empty();
        $content['children'][] = BuilderContent::node('section', children: [
            BuilderContent::node('image', ['alt' => '']),
            BuilderContent::node('heading', ['text' => '  前後に空白  ']),
            BuilderContent::node('text', ['html' => '<p onclick="alert(1)">本文<script>alert(1)</script></p>']),
        ]);

        $this->putJson(route('admin.json.builder.top.update'), ['content' => $content, 'updated_at' => null])->assertOk();

        $children = PageBuilder::top()->draft_content['children'][0]['children'];
        $this->assertSame('', $children[0]['props']['alt']);
        $this->assertSame('  前後に空白  ', $children[1]['props']['text']);
        $this->assertSame('<p>本文</p>', $children[2]['props']['html']);
    }

    public function test_update_rejects_invalid_content_with_the_node_id(): void
    {
        $this->actingAsAdmin();
        $content = $this->content();
        $content['children'][0]['children'][] = $button = BuilderContent::node('button', ['href' => 'javascript:alert(1)']);

        $this->putJson(route('admin.json.builder.top.update'), ['content' => $content, 'updated_at' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nodes.'.$button['id']]);

        $this->putJson(route('admin.json.builder.top.update'), ['content' => ['version' => 99, 'children' => []], 'updated_at' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);

        $this->assertNull(PageBuilder::top());
    }

    public function test_update_rejects_saving_over_another_administrators_save(): void
    {
        $this->actingAsAdmin();
        $builder = PageBuilder::factory()->top()->create(['updated_at' => now()->subMinute()]);
        $known = $builder->updated_at->toIso8601String();

        // ほかの管理者が保存した
        $this->travel(5)->seconds();
        $builder->update(['draft_content' => $this->content('ほかの管理者の見出し')]);

        $this->putJson(route('admin.json.builder.top.update'), ['content' => $this->content(), 'updated_at' => $known])
            ->assertStatus(409)
            ->assertJsonPath('updated_at', $builder->fresh()->updated_at->toIso8601String());

        $this->assertSame('ほかの管理者の見出し', $builder->fresh()->draft_content['children'][0]['children'][0]['props']['text']);

        // 画面を開いたあとに別の管理者がビルダーを作った場合も上書きしない
        $singlePage = SinglePage::factory()->create();
        PageBuilder::factory()->create(['single_page_id' => $singlePage->id]);

        $this->putJson(route('admin.json.builder.single-pages.update', $singlePage), ['content' => $this->content(), 'updated_at' => null])
            ->assertStatus(409);
    }

    public function test_consecutive_saves_by_the_same_administrator_record_one_audit_log(): void
    {
        $this->actingAsAdmin();
        $updatedAt = null;

        foreach (['1 回目', '2 回目'] as $heading) {
            $updatedAt = $this->putJson(route('admin.json.builder.top.update'), ['content' => $this->content($heading), 'updated_at' => $updatedAt])
                ->assertOk()
                ->json('updated_at');
            $this->travel(1)->minutes();
        }

        $this->assertSame(1, AuditLog::query()->count());

        $this->travel(30)->minutes();
        $this->putJson(route('admin.json.builder.top.update'), ['content' => $this->content('3 回目'), 'updated_at' => $updatedAt])->assertOk();

        $this->assertSame(2, AuditLog::query()->count());
        $this->assertSame('3 回目', PageBuilder::top()->draft_content['children'][0]['children'][0]['props']['text']);
    }

    public function test_publish_copies_the_draft_to_the_published_content(): void
    {
        $this->actingAsAdmin();
        $builder = PageBuilder::factory()->top()->create(['draft_content' => $this->content('公開する見出し')]);

        $this->postJson(route('admin.json.builder.top.publish'), ['updated_at' => $builder->updated_at->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('published', true)
            ->assertJsonPath('has_unpublished_changes', false);

        $builder->refresh();
        $this->assertSame('公開する見出し', $builder->published_content['children'][0]['children'][0]['props']['text']);
        $this->assertNotNull($builder->published_at);

        $log = AuditLog::query()->sole();
        $this->assertSame(AuditAction::Published, $log->action);
        $this->assertSame(['nodes' => [null, 2]], $log->metadata);
    }

    public function test_publish_rejects_a_draft_that_no_longer_matches_the_definitions(): void
    {
        $this->actingAsAdmin();
        $content = $this->content();
        $content['children'][0]['children'][0]['type'] = 'removed-block';
        $builder = PageBuilder::factory()->top()->create(['draft_content' => $content]);

        $this->postJson(route('admin.json.builder.top.publish'), ['updated_at' => $builder->updated_at->toIso8601String()])
            ->assertUnprocessable();

        $this->assertNull($builder->fresh()->published_content);
    }

    public function test_publish_and_discard_require_an_existing_builder(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.json.builder.top.publish'), ['updated_at' => null])->assertNotFound();
        $this->postJson(route('admin.json.builder.top.discard'), ['updated_at' => null])->assertNotFound();
        $this->getJson(route('admin.json.builder.top.preview-url'))->assertNotFound();
    }

    public function test_discard_reverts_the_draft_to_the_published_content(): void
    {
        $this->actingAsAdmin();
        $builder = PageBuilder::factory()->top()->published()->create();
        $published = $builder->published_content;
        $builder->update(['draft_content' => $this->content('破棄する見出し')]);

        $this->postJson(route('admin.json.builder.top.discard'), ['updated_at' => $builder->updated_at->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('has_unpublished_changes', false);

        $this->assertSame($published, $builder->fresh()->draft_content);
        $this->assertSame(AuditAction::DraftDiscarded, AuditLog::query()->sole()->action);
    }

    public function test_discard_is_rejected_when_nothing_is_published(): void
    {
        $this->actingAsAdmin();
        $builder = PageBuilder::factory()->top()->create();

        $this->postJson(route('admin.json.builder.top.discard'), ['updated_at' => $builder->updated_at->toIso8601String()])
            ->assertUnprocessable();
    }

    public function test_preview_url_points_to_the_front_with_a_signed_api_query(): void
    {
        $this->actingAsAdmin();
        config(['app.front_url' => 'https://front.example.com']);
        $builder = PageBuilder::factory()->create();

        $url = $this->getJson(route('admin.json.builder.single-pages.preview-url', $builder->single_page_id))
            ->assertOk()
            ->json('url');

        $this->assertStringStartsWith('https://front.example.com/builder-preview?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame((string) $builder->id, $query['id']);

        // chococo のサーバーは、受け取った id・expires・signature でプレビュー API を呼ぶ
        $this->getJson('/api/builder-previews/'.$query['id'].'?'.http_build_query(['expires' => $query['expires'], 'signature' => $query['signature']]))
            ->assertOk()
            ->assertJsonPath('type', 'single_page');
    }

    public function test_store_image_saves_the_image_and_returns_its_path(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.json.builder.images'), ['image' => UploadedFile::fake()->image('photo.jpg', 800, 600)])
            ->assertCreated();

        $path = $response->json('path');
        $this->assertMatchesRegularExpression(BuilderContent::IMAGE_PATH_PATTERN, $path);
        Storage::disk('public')->assertExists($path);
        $response->assertJsonPath('url', Storage::disk('public')->url($path));

        $log = AuditLog::query()->sole();
        $this->assertSame(AuditAction::Uploaded, $log->action);
        $this->assertSame('page_builder_image', $log->subject_type);

        $this->postJson(route('admin.json.builder.images'), ['image' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
            ->assertUnprocessable();
    }

    public function test_soft_deleted_single_pages_are_not_found(): void
    {
        $this->actingAsAdmin();
        $singlePage = SinglePage::factory()->create();
        $singlePage->delete();

        $this->getJson(route('admin.json.builder.single-pages.show', $singlePage))->assertNotFound();
    }
}
