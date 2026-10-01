<?php

namespace Tests\Feature;

use App\Enums\LayoutBlockType;
use App\Enums\LayoutRegion;
use App\Enums\NavItemLinkType;
use App\Models\LayoutBlock;
use App\Models\LayoutNavItem;
use App\Models\PageBuilder;
use App\Models\SinglePage;
use App\Support\Builder\BlockDataResolver;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_items_follow_the_header_nav_menu(): void
    {
        $footer = LayoutBlock::factory()->create(['region' => LayoutRegion::Footer, 'block_type' => LayoutBlockType::NavMenu]);
        LayoutNavItem::factory()->create(['layout_block_id' => $footer->id, 'link_type' => NavItemLinkType::Url, 'label' => 'フッター', 'url' => '/footer', 'sort_order' => 0]);
        $header = LayoutBlock::factory()->create(['region' => LayoutRegion::Header, 'block_type' => LayoutBlockType::NavMenu]);
        LayoutNavItem::factory()->create(['layout_block_id' => $header->id, 'link_type' => NavItemLinkType::Url, 'label' => '会社', 'url' => '/company', 'sort_order' => 1]);
        LayoutNavItem::factory()->create(['layout_block_id' => $header->id, 'link_type' => NavItemLinkType::Url, 'label' => 'ホーム', 'url' => '/', 'sort_order' => 0]);

        $this->assertSame(
            [['label' => 'ホーム', 'path' => '/', 'prefix' => false], ['label' => '会社', 'path' => '/company', 'prefix' => false]],
            BlockDataResolver::navigationItems(['source' => 'site']),
        );
    }

    public function test_site_items_are_listed_automatically_without_a_nav_menu(): void
    {
        SinglePage::factory()->create(['title' => '会社概要', 'slug' => 'company', 'link_list_view' => true]);

        $items = BlockDataResolver::navigationItems(['source' => 'site']);

        $this->assertSame(['label' => 'Home', 'path' => '/', 'prefix' => false], $items[0]);
        $this->assertContains(['label' => '会社概要', 'path' => '/company', 'prefix' => false], $items);
    }

    public function test_pages_items_list_published_pages_in_the_link_list(): void
    {
        SinglePage::factory()->create(['title' => '会社概要', 'slug' => 'company', 'link_list_view' => true, 'sort_order' => 1]);
        SinglePage::factory()->create(['title' => 'お問い合わせ', 'slug' => 'contact', 'link_list_view' => true, 'sort_order' => 0]);
        SinglePage::factory()->create(['title' => '出さない', 'slug' => 'hidden', 'link_list_view' => false]);
        SinglePage::factory()->create(['title' => '公開前', 'slug' => 'future', 'link_list_view' => true, 'publication_start_datetime' => now()->addDay()]);

        $this->assertSame(
            [['label' => 'お問い合わせ', 'path' => '/contact', 'prefix' => false], ['label' => '会社概要', 'path' => '/company', 'prefix' => false]],
            BlockDataResolver::navigationItems(['source' => 'pages']),
        );
    }

    public function test_resolve_returns_the_items_of_the_block_without_saving_them(): void
    {
        SinglePage::factory()->create(['title' => '会社概要', 'slug' => 'company', 'link_list_view' => true]);
        $singlePage = SinglePage::factory()->create(['slug' => 'nav-page', 'use_builder' => true, 'link_list_view' => false]);
        $builder = PageBuilder::factory()->published()->create([
            'single_page_id' => $singlePage->id,
            'draft_content' => [
                'version' => SchemaMigrator::CURRENT_VERSION,
                'children' => [BuilderContent::node('section', children: [BuilderContent::node('navigation', ['source' => 'pages', 'direction' => 'vertical'])])],
            ],
        ]);

        $this->getJson(route('api.resolve', ['path' => '/nav-page']))
            ->assertOk()
            ->assertJsonPath('data.builder.children.0.children.0.type', 'navigation')
            ->assertJsonPath('data.builder.children.0.children.0.props.direction', 'vertical')
            ->assertJsonPath('data.builder.children.0.children.0.data.items', [['label' => '会社概要', 'path' => '/company', 'prefix' => false]]);

        $this->assertArrayNotHasKey('data', $builder->fresh()->published_content['children'][0]['children'][0]);
    }

    public function test_editor_preview_returns_the_items(): void
    {
        SinglePage::factory()->create(['title' => '会社概要', 'slug' => 'company', 'link_list_view' => true]);

        $this->getJson(route('admin.json.builder.navigation', ['source' => 'pages']))->assertUnauthorized();

        $this->actingAsAdmin();

        $this->getJson(route('admin.json.builder.navigation', ['source' => 'pages']))
            ->assertOk()
            ->assertJsonPath('items', [['label' => '会社概要', 'path' => '/company', 'prefix' => false]]);
    }
}
