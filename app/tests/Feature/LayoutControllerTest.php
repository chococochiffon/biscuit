<?php

namespace Tests\Feature;

use App\Enums\CallContentType;
use App\Enums\CallType;
use App\Enums\LayoutBlockType;
use App\Enums\LayoutPageType;
use App\Enums\LayoutRegion;
use App\Enums\SidebarPosition;
use App\Models\ContentModelRelation;
use App\Models\Layout;
use App\Models\LayoutBlock;
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
        $this->assertSame(SidebarPosition::Right, Layout::sidebarPositions()[LayoutPageType::Article->value]);
        $this->assertSame(SidebarPosition::None, Layout::sidebarPositions()[LayoutPageType::Top->value]);
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
        $this->assertSame(SidebarPosition::Left, Layout::sidebarPositions()[LayoutPageType::Top->value]);
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
}
