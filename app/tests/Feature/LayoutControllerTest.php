<?php

namespace Tests\Feature;

use App\Enums\CallContentType;
use App\Enums\CallType;
use App\Enums\LayoutBlockType;
use App\Enums\LayoutPageType;
use App\Enums\LayoutRegion;
use App\Enums\NavItemLinkType;
use App\Enums\SidebarPosition;
use App\Models\ContentModelRelation;
use App\Models\CustomPageType;
use App\Models\Layout;
use App\Models\LayoutBlock;
use App\Models\LayoutNavItem;
use App\Models\SinglePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutControllerTest extends TestCase
{
    use RefreshDatabase;

    private function relation(string $modelName, string $tableName): ContentModelRelation
    {
        return ContentModelRelation::factory()->create([
            'content_type' => CallContentType::Article,
            'model_name' => $modelName,
            'table_name' => $tableName,
        ]);
    }

    /**
     * すべてのページの種類のサイドバーの位置(指定がなければなし)。
     *
     * @param  array<int, SidebarPosition>  $positions
     * @return array<int, array{sidebar_position: int}>
     */
    private function layoutsInput(array $positions = []): array
    {
        return collect(LayoutPageType::cases())
            ->mapWithKeys(fn (LayoutPageType $pageType) => [
                $pageType->value => ['sidebar_position' => ($positions[$pageType->value] ?? SidebarPosition::None)->value],
            ])
            ->all();
    }

    public function test_guests_are_redirected_from_layout_page(): void
    {
        $this->get(route('admin.layouts.edit'))->assertRedirect(route('admin.login'));
    }

    public function test_edit_screen_can_be_rendered_with_blocks_of_every_type(): void
    {
        $this->actingAsAdmin();
        $articles = $this->relation('Article', 'articles');

        foreach (LayoutBlockType::cases() as $blockType) {
            $block = LayoutBlock::factory()->state(['block_type' => $blockType]);
            ($blockType === LayoutBlockType::CallContent ? $block->callContent(relation: $articles) : $block)->create();
        }

        $response = $this->get(route('admin.layouts.edit'));

        $response->assertOk();
        $response->assertSee('id="layout-blocks"', false);
        $response->assertSee('layout-block-row-template', false);
    }

    public function test_update_saves_sidebar_positions_and_blocks_in_region_order(): void
    {
        $this->actingAsAdmin();
        $articles = $this->relation('Article', 'articles');

        $response = $this->put(route('admin.layouts.update'), [
            'layouts' => $this->layoutsInput([LayoutPageType::Article->value => SidebarPosition::Right]),
            'blocks' => [
                ['region' => LayoutRegion::Header->value, 'block_type' => LayoutBlockType::NavMenu->value, 'sort_order' => 1],
                ['region' => LayoutRegion::Header->value, 'block_type' => LayoutBlockType::SiteTitle->value, 'sort_order' => 0],
                [
                    'region' => LayoutRegion::Sidebar->value,
                    'block_type' => LayoutBlockType::CallContent->value,
                    'title' => '新着記事',
                    'call_type' => CallType::LinkList->value,
                    'content_model_relation_id' => $articles->id,
                    'view_count' => 5,
                    'sort_order' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.layouts.edit'));
        $response->assertSessionHasNoErrors();
        $this->assertSame(SidebarPosition::Right, Layout::forPageTypes()[LayoutPageType::Article->value]->sidebar_position);
        $this->assertSame(SidebarPosition::None, Layout::forPageTypes()[LayoutPageType::Top->value]->sidebar_position);
        $this->assertSame(
            [LayoutBlockType::SiteTitle, LayoutBlockType::NavMenu],
            LayoutBlock::query()->where('region', LayoutRegion::Header)->ordered()->pluck('block_type')->all()
        );
        $this->assertDatabaseHas('layout_blocks', [
            'region' => LayoutRegion::Sidebar->value,
            'block_type' => LayoutBlockType::CallContent->value,
            'title' => '新着記事',
            'call_type' => CallType::LinkList->value,
            'content_model_relation_id' => $articles->id,
            'view_count' => 5,
        ]);
    }

    public function test_update_keeps_one_layout_per_page_type(): void
    {
        $this->actingAsAdmin();

        $this->put(route('admin.layouts.update'), ['layouts' => $this->layoutsInput()]);
        $this->put(route('admin.layouts.update'), ['layouts' => $this->layoutsInput([LayoutPageType::Top->value => SidebarPosition::Left])]);

        $this->assertSame(count(LayoutPageType::cases()), Layout::query()->count());
        $this->assertSame(SidebarPosition::Left, Layout::forPageTypes()[LayoutPageType::Top->value]->sidebar_position);
    }

    public function test_update_moves_existing_blocks_and_soft_deletes_blocks_not_submitted(): void
    {
        $this->actingAsAdmin();
        $moved = LayoutBlock::factory()->create(['region' => LayoutRegion::Header, 'block_type' => LayoutBlockType::SocialLinks]);
        $removed = LayoutBlock::factory()->create(['region' => LayoutRegion::Footer, 'block_type' => LayoutBlockType::Copyright]);

        $this->put(route('admin.layouts.update'), [
            'layouts' => $this->layoutsInput(),
            'blocks' => [
                ['id' => $moved->id, 'region' => LayoutRegion::Footer->value, 'block_type' => LayoutBlockType::SocialLinks->value],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(LayoutRegion::Footer, $moved->fresh()->region);
        $this->assertSoftDeleted('layout_blocks', ['id' => $removed->id]);
    }

    public function test_update_discards_fields_that_the_block_type_does_not_use(): void
    {
        $this->actingAsAdmin();
        $articles = $this->relation('Article', 'articles');

        $this->put(route('admin.layouts.update'), [
            'layouts' => $this->layoutsInput(),
            'blocks' => [
                [
                    'region' => LayoutRegion::Footer->value,
                    'block_type' => LayoutBlockType::Copyright->value,
                    'title' => '使わない見出し',
                    'call_type' => CallType::TileList->value,
                    'content_model_relation_id' => $articles->id,
                    'view_count' => 0,
                    'content' => '<p>使わない本文</p>',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('layout_blocks', [
            'block_type' => LayoutBlockType::Copyright->value,
            'title' => null,
            'call_type' => null,
            'content_model_relation_id' => null,
            'view_count' => null,
            'content' => null,
        ]);
    }

    public function test_update_sanitizes_free_text(): void
    {
        $this->actingAsAdmin();

        $this->put(route('admin.layouts.update'), [
            'layouts' => $this->layoutsInput(),
            'blocks' => [
                [
                    'region' => LayoutRegion::Sidebar->value,
                    'block_type' => LayoutBlockType::FreeText->value,
                    'title' => 'お知らせ',
                    'content' => '<p onclick="alert(1)"><strong>営業時間</strong><script>alert(1)</script></p><p><a href="javascript:alert(1)">リンク</a></p>',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            '<p><strong>営業時間</strong></p><p><a>リンク</a></p>',
            LayoutBlock::query()->sole()->content
        );
    }

    public function test_update_rejects_call_content_combinations_not_allowed_in_others_place(): void
    {
        $this->actingAsAdmin();
        $gallery = $this->relation('GalleryImage', 'gallery_images');
        $articles = $this->relation('Article', 'articles');

        $response = $this->put(route('admin.layouts.update'), [
            'layouts' => $this->layoutsInput(),
            'blocks' => [
                [
                    'region' => LayoutRegion::Sidebar->value,
                    'block_type' => LayoutBlockType::CallContent->value,
                    'call_type' => CallType::TileList->value,
                    'content_model_relation_id' => $articles->id,
                    'view_count' => 3,
                ],
                [
                    'region' => LayoutRegion::Sidebar->value,
                    'block_type' => LayoutBlockType::CallContent->value,
                    'call_type' => CallType::LinkList->value,
                    'content_model_relation_id' => $gallery->id,
                    'view_count' => 3,
                ],
                [
                    'region' => LayoutRegion::Sidebar->value,
                    'block_type' => LayoutBlockType::CallContent->value,
                    'call_type' => CallType::Link->value,
                    'content_model_relation_id' => $articles->id,
                    'view_count' => 3,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['blocks.0.call_type', 'blocks.1.content_model_relation_id', 'blocks.2.view_count']);
        $this->assertSame(0, LayoutBlock::query()->count());
    }

    public function test_update_requires_call_content_fields_and_sidebar_positions(): void
    {
        $this->actingAsAdmin();

        $response = $this->put(route('admin.layouts.update'), [
            'blocks' => [
                ['region' => LayoutRegion::Header->value, 'block_type' => LayoutBlockType::CallContent->value],
                ['region' => 9, 'block_type' => 99],
            ],
        ]);

        $response->assertSessionHasErrors([
            'layouts.'.LayoutPageType::Top->value.'.sidebar_position',
            'blocks.0.call_type',
            'blocks.0.content_model_relation_id',
            'blocks.0.view_count',
            'blocks.1.region',
            'blocks.1.block_type',
        ]);
    }

    public function test_update_saves_nav_menu_items_in_order(): void
    {
        $this->actingAsAdmin();
        $page = SinglePage::factory()->create();
        $type = CustomPageType::factory()->create();

        $this->put(route('admin.layouts.update'), [
            'layouts' => $this->layoutsInput(),
            'blocks' => [
                [
                    'region' => LayoutRegion::Header->value,
                    'block_type' => LayoutBlockType::NavMenu->value,
                    'nav_items' => [
                        ['link_type' => NavItemLinkType::Url->value, 'label' => 'Home', 'url' => '/', 'single_page_id' => $page->id, 'sort_order' => 0],
                        ['link_type' => NavItemLinkType::CustomPageType->value, 'custom_page_type_id' => $type->id, 'url' => '/ignored', 'sort_order' => 2],
                        ['link_type' => NavItemLinkType::SinglePage->value, 'label' => '', 'single_page_id' => $page->id, 'sort_order' => 1],
                    ],
                ],
            ],
        ])->assertSessionHasNoErrors();

        $block = LayoutBlock::query()->sole();
        $items = $block->navItems()->ordered()->get();
        $this->assertSame(
            [NavItemLinkType::Url, NavItemLinkType::SinglePage, NavItemLinkType::CustomPageType],
            $items->pluck('link_type')->all()
        );
        // リンク先の種類で使わない項目は保存しない
        $this->assertSame(['/', null, null], $items->pluck('url')->all());
        $this->assertSame([null, $page->id, null], $items->pluck('single_page_id')->all());
        $this->assertSame([null, null, $type->id], $items->pluck('custom_page_type_id')->all());
        $this->assertNull($items[1]->label);
    }

    public function test_update_syncs_nav_items_and_removes_items_of_blocks_that_are_no_longer_nav_menus(): void
    {
        $this->actingAsAdmin();
        $navBlock = LayoutBlock::factory()->create(['region' => LayoutRegion::Header, 'block_type' => LayoutBlockType::NavMenu]);
        $kept = LayoutNavItem::factory()->create(['layout_block_id' => $navBlock->id, 'label' => '変更前']);
        $removed = LayoutNavItem::factory()->create(['layout_block_id' => $navBlock->id]);
        $changedBlock = LayoutBlock::factory()->create(['region' => LayoutRegion::Footer, 'block_type' => LayoutBlockType::NavMenu]);
        $orphan = LayoutNavItem::factory()->create(['layout_block_id' => $changedBlock->id]);

        $this->put(route('admin.layouts.update'), [
            'layouts' => $this->layoutsInput(),
            'blocks' => [
                [
                    'id' => $navBlock->id,
                    'region' => LayoutRegion::Header->value,
                    'block_type' => LayoutBlockType::NavMenu->value,
                    'nav_items' => [
                        ['id' => $kept->id, 'link_type' => NavItemLinkType::Url->value, 'label' => '変更後', 'url' => 'https://example.com'],
                    ],
                ],
                [
                    'id' => $changedBlock->id,
                    'region' => LayoutRegion::Footer->value,
                    'block_type' => LayoutBlockType::Copyright->value,
                    'nav_items' => [['id' => $orphan->id, 'link_type' => NavItemLinkType::Url->value, 'label' => '捨てる', 'url' => '/']],
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('変更後', $kept->fresh()->label);
        $this->assertSoftDeleted('layout_nav_items', ['id' => $removed->id]);
        $this->assertSoftDeleted('layout_nav_items', ['id' => $orphan->id]);
    }

    public function test_update_rejects_invalid_nav_items(): void
    {
        $this->actingAsAdmin();

        $response = $this->put(route('admin.layouts.update'), [
            'layouts' => $this->layoutsInput(),
            'blocks' => [
                [
                    'region' => LayoutRegion::Header->value,
                    'block_type' => LayoutBlockType::NavMenu->value,
                    'nav_items' => [
                        ['link_type' => NavItemLinkType::Url->value, 'label' => '危険', 'url' => 'javascript:alert(1)'],
                        ['link_type' => NavItemLinkType::Url->value, 'label' => '', 'url' => '/about'],
                        ['link_type' => NavItemLinkType::SinglePage->value],
                        ['link_type' => NavItemLinkType::CustomPageType->value, 'custom_page_type_id' => 999],
                    ],
                ],
            ],
        ]);

        $response->assertSessionHasErrors([
            'blocks.0.nav_items.0.url',
            'blocks.0.nav_items.1.label',
            'blocks.0.nav_items.2.single_page_id',
            'blocks.0.nav_items.3.custom_page_type_id',
        ]);
        $this->assertSame(0, LayoutBlock::query()->count());
    }

    public function test_update_rejects_nav_items_over_the_limit(): void
    {
        $this->actingAsAdmin();
        config(['limits.layout_nav_items' => 1]);
        $item = ['link_type' => NavItemLinkType::Url->value, 'label' => 'Home', 'url' => '/'];

        $this->put(route('admin.layouts.update'), [
            'layouts' => $this->layoutsInput(),
            'blocks' => [['region' => LayoutRegion::Header->value, 'block_type' => LayoutBlockType::NavMenu->value, 'nav_items' => [$item, $item]]],
        ])->assertSessionHasErrors('blocks.0.nav_items');
    }

    public function test_edit_screen_restores_nav_items_after_a_validation_error(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.layouts.edit'))->put(route('admin.layouts.update'), [
            'blocks' => [
                [
                    'region' => LayoutRegion::Header->value,
                    'block_type' => LayoutBlockType::NavMenu->value,
                    'nav_items' => [['link_type' => NavItemLinkType::Url->value, 'label' => '入力した項目', 'url' => '/typed']],
                ],
            ],
        ])->assertRedirect(route('admin.layouts.edit'));

        $this->get(route('admin.layouts.edit'))
            ->assertSee('value="入力した項目"', false)
            ->assertSee('name="blocks[0][nav_items][0][url]"', false);
    }

    public function test_update_saves_whether_to_show_breadcrumbs_for_each_page_type(): void
    {
        $this->actingAsAdmin();
        $layouts = $this->layoutsInput();
        $layouts[LayoutPageType::Article->value]['show_breadcrumbs'] = '1';

        $this->put(route('admin.layouts.update'), ['layouts' => $layouts])->assertSessionHasNoErrors();

        $saved = Layout::forPageTypes();
        $this->assertTrue($saved[LayoutPageType::Article->value]->show_breadcrumbs);
        // チェックを外した(送信されなかった)種類は表示しない
        $this->assertFalse($saved[LayoutPageType::SinglePage->value]->show_breadcrumbs);
    }

    public function test_edit_screen_checks_breadcrumbs_except_top_by_default(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.layouts.edit'));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertDoesNotMatchRegularExpression('/name="layouts\\['.LayoutPageType::Top->value.'\\]\\[show_breadcrumbs\\]"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/name="layouts\\['.LayoutPageType::Article->value.'\\]\\[show_breadcrumbs\\]"[^>]*checked/', $html);
    }
}
