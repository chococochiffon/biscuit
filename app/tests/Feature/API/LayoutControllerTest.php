<?php

namespace Tests\Feature\API;

use App\Enums\CallContentType;
use App\Enums\CallType;
use App\Enums\LayoutBlockType;
use App\Enums\LayoutPageType;
use App\Enums\LayoutRegion;
use App\Enums\SidebarPosition;
use App\Models\Article;
use App\Models\ContentModelRelation;
use App\Models\Layout;
use App\Models\LayoutBlock;
use App\Models\SinglePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_no_sidebar_and_empty_regions_when_nothing_is_registered(): void
    {
        $response = $this->getJson(route('api.layout.show'));

        $response->assertOk();
        $response->assertExactJson([
            'data' => [
                'pages' => [
                    'top' => ['sidebar_position' => 'none'],
                    'article' => ['sidebar_position' => 'none'],
                    'single_page' => ['sidebar_position' => 'none'],
                    'other' => ['sidebar_position' => 'none'],
                ],
                'regions' => ['header' => [], 'sidebar' => [], 'footer' => []],
            ],
        ]);
    }

    public function test_show_returns_sidebar_positions_and_blocks_by_region_in_order(): void
    {
        Layout::factory()->create(['page_type' => LayoutPageType::Article, 'sidebar_position' => SidebarPosition::Right]);
        LayoutBlock::factory()->create(['region' => LayoutRegion::Header, 'block_type' => LayoutBlockType::NavMenu, 'sort_order' => 1]);
        LayoutBlock::factory()->create(['region' => LayoutRegion::Header, 'block_type' => LayoutBlockType::SiteTitle, 'sort_order' => 0]);
        LayoutBlock::factory()->freeText('<p>営業時間</p>')->create(['region' => LayoutRegion::Footer, 'title' => 'お知らせ']);
        LayoutBlock::factory()->create(['region' => LayoutRegion::Footer, 'block_type' => LayoutBlockType::Copyright, 'sort_order' => 1])->delete();

        $response = $this->getJson(route('api.layout.show'));

        $response->assertOk();
        $response->assertJsonPath('data.pages.article.sidebar_position', 'right');
        $response->assertJsonPath('data.pages.top.sidebar_position', 'none');
        $response->assertJsonPath('data.regions.header.0.block_type', 'site_title');
        $response->assertJsonPath('data.regions.header.1.block_type', 'nav_menu');
        $response->assertJsonCount(1, 'data.regions.footer');
        $response->assertJsonPath('data.regions.footer.0.block_type', 'free_text');
        $response->assertJsonPath('data.regions.footer.0.title', 'お知らせ');
        $response->assertJsonPath('data.regions.footer.0.content', '<p>営業時間</p>');
    }

    public function test_nav_menu_returns_published_single_pages_for_link_list(): void
    {
        $linked = SinglePage::factory()->create(['link_list_view' => true, 'title' => 'About']);
        SinglePage::factory()->create(['link_list_view' => false]);
        LayoutBlock::factory()->create(['region' => LayoutRegion::Header, 'block_type' => LayoutBlockType::NavMenu]);

        $response = $this->getJson(route('api.layout.show'));

        $response->assertJsonCount(1, 'data.regions.header.0.single_pages');
        $response->assertJsonPath('data.regions.header.0.single_pages.0.id', $linked->id);
        $response->assertJsonPath('data.regions.header.0.custom_page_types', []);
    }

    public function test_call_content_block_is_resolved_like_call_content_api(): void
    {
        $relation = ContentModelRelation::factory()->create([
            'content_type' => CallContentType::Article,
            'model_name' => 'Article',
            'table_name' => 'articles',
        ]);
        Article::factory()->published()->count(3)->create();
        Article::factory()->pending()->create();
        LayoutBlock::factory()
            ->callContent(CallType::LinkList, $relation, 2)
            ->create(['region' => LayoutRegion::Sidebar, 'title' => '新着記事']);

        $response = $this->getJson(route('api.layout.show'));

        $response->assertOk();
        $response->assertJsonPath('data.regions.sidebar.0.block_type', 'call_content');
        $response->assertJsonPath('data.regions.sidebar.0.call_content.call_type', 'link_list');
        $response->assertJsonPath('data.regions.sidebar.0.call_content.title', '新着記事');
        $response->assertJsonCount(2, 'data.regions.sidebar.0.call_content.articles');
    }
}
