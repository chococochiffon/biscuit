<?php

namespace App\Support\CallContent;

use App\Models\SinglePage;
use Illuminate\Database\Eloquent\Collection;

/**
 * 呼び出しコンテンツ(CallContent)がSinglePageを参照する場合の実データ取得を担う。
 */
class SinglePageContentSource
{
    /**
     * 短文表示用に、Topページ表示対象(top_page_view=true)の固定ページを表示順で5件取得する。
     *
     * @return Collection<int, SinglePage>
     */
    public function getShortSentence(): Collection
    {
        return SinglePage::query()
            ->where('top_page_view', true)
            ->orderBy('sort_order')
            ->take(5)
            ->get();
    }

    /**
     * 原文表示用に、表示順が先頭の固定ページを1件、詳細(single_page_details)込みで取得する。
     */
    public function getOriginalText(): ?SinglePage
    {
        return SinglePage::query()
            ->with('details')
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * リンクリスト表示用に、Topページ表示対象(top_page_view=true)の固定ページを表示順で10件取得する。
     *
     * @return Collection<int, SinglePage>
     */
    public function getLinkList(): Collection
    {
        return SinglePage::query()
            ->where('top_page_view', true)
            ->orderBy('sort_order')
            ->take(10)
            ->get();
    }

    /**
     * リンク表示用に、表示順が先頭の固定ページを1件取得する。
     */
    public function getLink(): ?SinglePage
    {
        return SinglePage::query()->orderBy('sort_order')->first();
    }
}
