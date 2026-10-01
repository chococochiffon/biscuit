<?php

namespace Tests\Feature;

use App\Models\PageBuilder;
use App\Models\SinglePage;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreadcrumbBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_receives_the_breadcrumbs_of_the_page(): void
    {
        $this->actingAsAdmin();
        SinglePage::factory()->create(['parent_path' => null, 'slug' => 'company', 'title' => '会社']);
        $singlePage = SinglePage::factory()->create(['parent_path' => 'company', 'slug' => 'about', 'title' => '会社概要']);

        $this->getJson(route('admin.json.builder.single-pages.show', $singlePage))
            ->assertOk()
            ->assertJsonPath('breadcrumbs', [
                ['label' => 'Home', 'path' => '/'],
                ['label' => '会社', 'path' => '/company'],
                ['label' => '会社概要', 'path' => '/company/about'],
            ]);

        $this->getJson(route('admin.json.builder.top.show'))->assertOk()->assertJsonPath('breadcrumbs', []);
    }

    public function test_public_page_returns_the_block_with_its_look_and_the_page_breadcrumbs(): void
    {
        $singlePage = SinglePage::factory()->create(['slug' => 'about', 'title' => '会社概要', 'use_builder' => true]);
        PageBuilder::factory()->published()->create([
            'single_page_id' => $singlePage->id,
            'draft_content' => [
                'version' => SchemaMigrator::CURRENT_VERSION,
                'children' => [BuilderContent::node('section', children: [BuilderContent::node('breadcrumb', ['separator' => 'chevron', 'showCurrent' => false])])],
            ],
        ]);

        $response = $this->getJson(route('api.resolve', ['path' => '/about']))->assertOk();

        // パンくずのブロックはデータを持たず、公開側はページの breadcrumbs を使う
        $response->assertJsonPath('data.builder.children.0.children.0.type', 'breadcrumb')
            ->assertJsonPath('data.builder.children.0.children.0.props.separator', 'chevron')
            ->assertJsonPath('data.builder.children.0.children.0.props.showCurrent', false)
            ->assertJsonMissingPath('data.builder.children.0.children.0.data')
            ->assertJsonPath('breadcrumbs.1', ['label' => '会社概要', 'path' => '/about']);
    }
}
