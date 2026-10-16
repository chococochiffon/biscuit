<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * 並び順(sort_order)を持つモデル用の共通処理。
 */
trait HasSortOrder
{
    /**
     * 並び順(sort_order、同順なら id)で並べる。
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * 末尾に追加する行の並び順(今の最大値 + 1。行がなければ 0)。
     * 論理削除した行も含めるなど対象を絞り込む/広げる場合は、そのクエリを渡す。
     *
     * @param  Builder<static>|null  $query
     */
    public static function nextSortOrder(?Builder $query = null): int
    {
        return (int) (($query ?? static::query())->max('sort_order') ?? -1) + 1;
    }

    /**
     * 先頭に追加する行の並び順。今ある行の並び順を 1 つずつ後ろへずらし、空いた 0 を返す。
     * 更新日時は変えない(並び順をずらしただけで「更新」扱いにしない)。行の追加と同じトランザクションの中で呼ぶ。
     *
     * @param  Builder<static>|null  $query
     */
    public static function prependSortOrder(?Builder $query = null): int
    {
        ($query ?? static::query())->toBase()->increment('sort_order');

        return 0;
    }
}
