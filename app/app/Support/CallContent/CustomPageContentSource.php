<?php

namespace App\Support\CallContent;

use App\Models\CustomPages\CustomPageEntry;
use App\Models\CustomPageType;
use Illuminate\Database\Eloquent\Collection;

/**
 * 呼び出しコンテンツ(CallContent)がカスタムページを参照する場合の実データ取得を担う。
 * どの呼び出し方も公開中のページ(CustomPageEntry::publishedQueryFor())を公開側の並び順で取得する
 * (記事型は公開開始日時の新しい順、固定ページ型は表示順)。
 */
class CustomPageContentSource
{
    /**
     * リンクリスト・アーカイブ表示用に、公開中のページを指定件数取得する。
     *
     * @return Collection<int, CustomPageEntry>
     */
    public function getList(CustomPageType $type, int $count): Collection
    {
        return CustomPageEntry::publishedQueryFor($type)->take($count)->get();
    }

    /**
     * リンク表示用に、公開中のページの先頭の 1 件を取得する。
     */
    public function getLink(CustomPageType $type): ?CustomPageEntry
    {
        return CustomPageEntry::publishedQueryFor($type)->first();
    }
}
