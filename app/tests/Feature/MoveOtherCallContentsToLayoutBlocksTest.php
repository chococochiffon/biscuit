<?php

namespace Tests\Feature;

use App\Enums\CallContentPlace;
use App\Enums\CallContentType;
use App\Enums\CallType;
use App\Enums\LayoutBlockType;
use App\Enums\LayoutRegion;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
use App\Models\LayoutBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * サイト設定の呼び出しコンテンツ「その他」をレイアウトの部品へ移すマイグレーションを確認する。
 */
class MoveOtherCallContentsToLayoutBlocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_moves_other_call_contents_except_single_pages_to_the_end_of_footer(): void
    {
        $articles = ContentModelRelation::factory()->create(['content_type' => CallContentType::Article, 'model_name' => 'Article', 'table_name' => 'articles']);
        $singlePages = ContentModelRelation::factory()->create(['content_type' => CallContentType::SinglePage, 'model_name' => 'SinglePage', 'table_name' => 'single_pages']);
        LayoutBlock::factory()->create(['region' => LayoutRegion::Footer, 'block_type' => LayoutBlockType::Copyright, 'sort_order' => 4]);
        $navPages = CallContent::factory()->create(['place' => 3, 'call_type' => CallType::LinkList, 'content_model_relation_id' => $singlePages->id, 'sort_order' => 0]);
        $recent = CallContent::factory()->create(['place' => 3, 'call_type' => CallType::LinkList, 'content_model_relation_id' => $articles->id, 'title' => '最近の記事', 'view_count' => 5, 'sort_order' => 1]);
        $top = CallContent::factory()->create(['place' => CallContentPlace::Top, 'call_type' => CallType::Archive, 'content_model_relation_id' => $articles->id]);

        (require database_path('migrations/2026_09_29_000003_move_other_call_contents_to_layout_blocks.php'))->up();

        $this->assertSoftDeleted('call_contents', ['id' => $navPages->id]);
        $this->assertSoftDeleted('call_contents', ['id' => $recent->id]);
        $this->assertNotSoftDeleted('call_contents', ['id' => $top->id]);

        $moved = LayoutBlock::query()->where('block_type', LayoutBlockType::CallContent)->sole();
        $this->assertSame(LayoutRegion::Footer, $moved->region);
        $this->assertSame('最近の記事', $moved->title);
        $this->assertSame(CallType::LinkList, $moved->call_type);
        $this->assertSame($articles->id, $moved->content_model_relation_id);
        $this->assertSame(5, $moved->view_count);
        $this->assertSame(5, $moved->sort_order);
    }
}
