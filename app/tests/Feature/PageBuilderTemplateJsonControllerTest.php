<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\PageBuilderTemplate;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageBuilderTemplateJsonControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function content(): array
    {
        return BuilderContent::withDefaultLayout([
            'version' => SchemaMigrator::CURRENT_VERSION,
            'children' => [
                BuilderContent::node('section', children: [
                    BuilderContent::node('image', ['alt' => '']),
                    BuilderContent::node('text', ['html' => '<p onclick="alert(1)">本文</p>']),
                ]),
            ],
        ]);
    }

    public function test_guests_cannot_use_the_templates(): void
    {
        $template = PageBuilderTemplate::factory()->create();

        $this->getJson(route('admin.json.builder-templates.index'))->assertUnauthorized();
        $this->postJson(route('admin.json.builder-templates.store'), ['name' => 'x', 'content' => $this->content()])->assertUnauthorized();
        $this->deleteJson(route('admin.json.builder-templates.destroy', $template))->assertUnauthorized();
    }

    public function test_index_lists_templates_with_their_content(): void
    {
        $this->actingAsAdmin();
        $first = PageBuilderTemplate::factory()->create(['name' => '1 つ目']);
        PageBuilderTemplate::factory()->create(['name' => '2 つ目']);
        PageBuilderTemplate::factory()->create()->delete();

        $this->getJson(route('admin.json.builder-templates.index'))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $first->id)
            ->assertJsonPath('0.name', '1 つ目')
            ->assertJsonPath('0.node_count', 2)
            ->assertJsonPath('0.content.version', SchemaMigrator::CURRENT_VERSION)
            ->assertJsonPath('0.content.children.0.type', 'section');
    }

    public function test_store_saves_the_content_as_a_template(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.json.builder-templates.store'), [
            'name' => 'キャンペーン',
            'description' => '',
            'content' => $this->content(),
        ])->assertCreated()->assertJsonPath('name', 'キャンペーン');

        $template = PageBuilderTemplate::query()->sole();
        $this->assertNull($template->description);
        // 空の代替テキストはそのまま、本文の HTML は無害化して保存する
        $this->assertSame('', $template->content['children'][0]['children'][0]['props']['alt']);
        $this->assertSame('<p>本文</p>', $template->content['children'][0]['children'][1]['props']['html']);

        $log = AuditLog::query()->sole();
        $this->assertSame(AuditAction::Created, $log->action);
        $this->assertSame('page_builder_template', $log->subject_type);
        $this->assertSame('キャンペーン', $log->subject_label);
    }

    public function test_store_rejects_missing_name_and_invalid_content(): void
    {
        $this->actingAsAdmin();
        $content = $this->content();
        $content['children'][0]['children'][] = $button = BuilderContent::node('button', ['href' => 'javascript:alert(1)']);

        $this->postJson(route('admin.json.builder-templates.store'), ['name' => '', 'content' => $this->content()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->postJson(route('admin.json.builder-templates.store'), ['name' => '不正', 'content' => $content])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nodes.'.$button['id']]);

        $this->assertSame(0, PageBuilderTemplate::query()->count());
    }

    public function test_destroy_soft_deletes_the_template(): void
    {
        $this->actingAsAdmin();
        $template = PageBuilderTemplate::factory()->create();

        $this->deleteJson(route('admin.json.builder-templates.destroy', $template))->assertNoContent();

        $this->assertSoftDeleted($template);
        $this->assertSame(AuditAction::Deleted, AuditLog::query()->sole()->action);
    }
}
