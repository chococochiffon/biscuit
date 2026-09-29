<?php

namespace Database\Seeders;

use App\Enums\CallType;
use App\Enums\LayoutBlockType;
use App\Enums\LayoutPageType;
use App\Enums\LayoutRegion;
use App\Enums\SidebarPosition;
use App\Models\ContentModelRelation;
use App\Models\Layout;
use App\Models\LayoutBlock;
use Illuminate\Database\Seeder;

class LayoutSeeder extends Seeder
{
    /**
     * 公開側のレイアウトの初期値を登録する。
     * ヘッダーにサイトタイトルとナビメニュー、フッターにコピーライトと SNS リンクを置き、
     * 記事ページだけ右のサイドバーに新着記事を並べる。部品が登録済みなら部品は登録しない。
     * データ種別の紐付け(ContentModelRelationSeeder)の後に実行する。
     */
    public function run(): void
    {
        $sidebarPositions = [
            LayoutPageType::Top->value => SidebarPosition::None,
            LayoutPageType::Article->value => SidebarPosition::Right,
            LayoutPageType::SinglePage->value => SidebarPosition::None,
            LayoutPageType::Other->value => SidebarPosition::None,
        ];

        foreach ($sidebarPositions as $pageType => $sidebarPosition) {
            Layout::query()->firstOrCreate(['page_type' => $pageType], ['sidebar_position' => $sidebarPosition]);
        }

        if (LayoutBlock::query()->exists()) {
            return;
        }

        $articleRelation = ContentModelRelation::query()->where('model_name', 'Article')->first();

        $blocks = [
            ['region' => LayoutRegion::Header, 'block_type' => LayoutBlockType::SiteTitle],
            ['region' => LayoutRegion::Header, 'block_type' => LayoutBlockType::NavMenu],
            ['region' => LayoutRegion::Footer, 'block_type' => LayoutBlockType::Copyright],
            ['region' => LayoutRegion::Footer, 'block_type' => LayoutBlockType::SocialLinks],
        ];

        if ($articleRelation) {
            $blocks[] = [
                'region' => LayoutRegion::Sidebar,
                'block_type' => LayoutBlockType::CallContent,
                'title' => '新着記事',
                'call_type' => CallType::LinkList,
                'content_model_relation_id' => $articleRelation->id,
                'view_count' => 5,
            ];
        }

        foreach ($blocks as $index => $block) {
            LayoutBlock::query()->create($block + ['sort_order' => $index]);
        }
    }
}
