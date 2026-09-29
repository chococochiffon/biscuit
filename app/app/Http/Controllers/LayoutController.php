<?php

namespace App\Http\Controllers;

use App\Enums\LayoutBlockType;
use App\Enums\LayoutPageType;
use App\Http\Controllers\Concerns\SyncsSortableRows;
use App\Http\Requests\UpdateLayoutRequest;
use App\Models\ContentModelRelation;
use App\Models\Layout;
use App\Models\LayoutBlock;
use App\Models\SiteSetting;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * レイアウト管理: 公開側のページの種類ごとのサイドバーの位置と、ヘッダー・サイドバー・フッターに置く部品を 1 画面で編集する。
 */
class LayoutController extends Controller
{
    use SyncsSortableRows;

    /**
     * Show the form for editing the resource.
     */
    public function edit(): View
    {
        $sidebarPositions = Layout::sidebarPositions();
        $blocks = LayoutBlock::query()->ordered()->get();
        $contentModelRelations = ContentModelRelation::all(['id', 'content_type', 'model_name', 'table_name']);
        $frontUrl = SiteSetting::current()?->front_url;

        return view('admin.layouts.edit', compact('sidebarPositions', 'blocks', 'contentModelRelations', 'frontUrl'));
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
                    ['sidebar_position' => $request->validated("layouts.{$pageType->value}.sidebar_position")],
                );
            }

            $this->syncBlocks($request->validated('blocks', []));
        });

        return redirect()->route('admin.layouts.edit')->with('status', __('レイアウトを更新しました。'));
    }

    /**
     * フォームから送信された部品(blocks)の内容にデータベースを同期する。
     * (作成/更新/削除と並び順の扱いは SyncsSortableRows::syncSortableRows() を参照。並び順は領域ごとの画面上の順)
     * 部品の種類で使わない項目(見出しを持たない部品の見出しなど)は null にし、自由テキストの本文は許可したタグ・属性だけにして保存する。
     *
     * @param  array<int, array{id?: int|string|null, region: int|string, block_type: int|string, title?: string|null, subtitle?: string|null, call_type?: int|string, content_model_relation_id?: int|string, view_count?: int|string, content?: string|null, sort_order?: int|string|null}>  $rows
     */
    private function syncBlocks(array $rows): void
    {
        $this->syncSortableRows(LayoutBlock::query(), $rows, function (array $row): array {
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
}
