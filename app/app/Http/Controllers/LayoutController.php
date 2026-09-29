<?php

namespace App\Http\Controllers;

use App\Enums\LayoutBlockType;
use App\Enums\LayoutPageType;
use App\Enums\NavItemLinkType;
use App\Http\Controllers\Concerns\SyncsSortableRows;
use App\Http\Requests\UpdateLayoutRequest;
use App\Models\ContentModelRelation;
use App\Models\CustomPageType;
use App\Models\Layout;
use App\Models\LayoutBlock;
use App\Models\LayoutNavItem;
use App\Models\SinglePage;
use App\Models\SiteSetting;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * レイアウト管理: 公開側のページの種類ごとのサイドバーの位置・パンくずを表示するかと、ヘッダー・サイドバー・フッターに置く部品を 1 画面で編集する。
 */
class LayoutController extends Controller
{
    use SyncsSortableRows;

    /**
     * Show the form for editing the resource.
     */
    public function edit(): View
    {
        $layouts = Layout::forPageTypes();
        $blocks = LayoutBlock::query()->with(['navItems' => fn ($query) => $query->ordered()])->ordered()->get();
        $contentModelRelations = ContentModelRelation::all(['id', 'content_type', 'model_name', 'table_name']);
        // ナビメニューの項目のリンク先の候補(公開期間外の固定ページも、公開されたらナビに出るよう選べる)
        $singlePages = SinglePage::query()->ordered()->get(['id', 'title', 'path']);
        $customPageTypes = CustomPageType::query()->ordered()->get();
        $frontUrl = SiteSetting::current()?->front_url;

        return view('admin.layouts.edit', compact('layouts', 'blocks', 'contentModelRelations', 'singlePages', 'customPageTypes', 'frontUrl'));
    }

    /**
     * Update the resource in storage.
     */
    public function update(UpdateLayoutRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            foreach (LayoutPageType::cases() as $pageType) {
                Layout::query()->updateOrCreate(
                    ['page_type' => $pageType],
                    [
                        'sidebar_position' => $request->validated("layouts.{$pageType->value}.sidebar_position"),
                        'show_breadcrumbs' => $request->boolean("layouts.{$pageType->value}.show_breadcrumbs"),
                    ],
                );
            }

            $blocks = $request->validated('blocks', []);
            $blockIds = $this->syncBlocks($blocks);
            $this->syncNavItems($blocks, $blockIds);
        });

        return redirect()->route('admin.layouts.edit')->with('status', __('レイアウトを更新しました。'));
    }

    /**
     * フォームから送信された部品(blocks)の内容にデータベースを同期する。
     * (作成/更新/削除と並び順の扱いは SyncsSortableRows::syncSortableRows() を参照。並び順は領域ごとの画面上の順)
     * 部品の種類で使わない項目(見出しを持たない部品の見出しなど)は null にし、自由テキストの本文は許可したタグ・属性だけにして保存する。
     *
     * @param  array<int, array{id?: int|string|null, region: int|string, block_type: int|string, title?: string|null, subtitle?: string|null, call_type?: int|string, content_model_relation_id?: int|string, view_count?: int|string, content?: string|null, sort_order?: int|string|null}>  $rows
     * @return array<int|string, int> 送信された行のキー → 保存した部品の id
     */
    private function syncBlocks(array $rows): array
    {
        return $this->syncSortableRows(LayoutBlock::query(), $rows, function (array $row): array {
            $blockType = LayoutBlockType::from((int) $row['block_type']);
            $isCallContent = $blockType === LayoutBlockType::CallContent;
            $hasHeading = $blockType->hasHeading();

            return [
                'region' => $row['region'],
                'block_type' => $blockType,
                'title' => $hasHeading ? ($row['title'] ?? null) : null,
                'subtitle' => $hasHeading ? ($row['subtitle'] ?? null) : null,
                'call_type' => $isCallContent ? $row['call_type'] : null,
                'content_model_relation_id' => $isCallContent ? $row['content_model_relation_id'] : null,
                'view_count' => $isCallContent ? $row['view_count'] : null,
                'content' => $blockType === LayoutBlockType::FreeText ? HtmlSanitizer::clean($row['content'] ?? null) : null,
            ];
        });
    }

    /**
     * ナビメニューの部品ごとに、送信された項目(nav_items)の内容にデータベースを同期する。
     * ナビメニューでなくなった部品・削除した部品の項目は削除(論理削除)する。
     * リンク先の種類で使わない項目(URL の項目の固定ページなど)は null にする。
     *
     * @param  array<int|string, array<string, mixed>>  $blocks  送信された部品の行
     * @param  array<int|string, int>  $blockIds  部品の行のキー → 保存した部品の id
     */
    private function syncNavItems(array $blocks, array $blockIds): void
    {
        $navBlockIds = [];

        foreach ($blocks as $key => $block) {
            if (LayoutBlockType::from((int) $block['block_type']) !== LayoutBlockType::NavMenu) {
                continue;
            }

            $navBlockIds[] = $blockIds[$key];

            $this->syncSortableRows(
                LayoutNavItem::query()->where('layout_block_id', $blockIds[$key]),
                $block['nav_items'] ?? [],
                function (array $row) use ($blockIds, $key): array {
                    $linkType = NavItemLinkType::from((int) $row['link_type']);

                    return [
                        'layout_block_id' => $blockIds[$key],
                        'link_type' => $linkType,
                        'label' => $row['label'] ?? null,
                        'url' => $linkType === NavItemLinkType::Url ? $row['url'] : null,
                        'single_page_id' => $linkType === NavItemLinkType::SinglePage ? $row['single_page_id'] : null,
                        'custom_page_type_id' => $linkType === NavItemLinkType::CustomPageType ? $row['custom_page_type_id'] : null,
                    ];
                },
            );
        }

        LayoutNavItem::query()->whereNotIn('layout_block_id', $navBlockIds)->delete();
    }
}
