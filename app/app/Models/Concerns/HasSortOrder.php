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
}
