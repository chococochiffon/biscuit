<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * 公開期間(publication_start_datetime/publication_end_datetime)を持つモデル用の共通処理。
 */
trait HasPublicationPeriod
{
    /**
     * 公開開始・公開終了を日付の範囲(Y-m-d、いずれも任意)で絞り込む。
     * 範囲の指定がある場合、公開終了が未設定のレコードは公開終了の条件に一致しない。
     *
     * @param  array{publication_start_from?: string|null, publication_start_to?: string|null, publication_end_from?: string|null, publication_end_to?: string|null}  $filters
     */
    #[Scope]
    protected function filterPublicationPeriod(Builder $query, array $filters): void
    {
        $columns = [
            'publication_start' => 'publication_start_datetime',
            'publication_end' => 'publication_end_datetime',
        ];

        foreach ($columns as $prefix => $column) {
            if (filled($filters["{$prefix}_from"] ?? null)) {
                $query->whereDate($column, '>=', $filters["{$prefix}_from"]);
            }

            if (filled($filters["{$prefix}_to"] ?? null)) {
                $query->whereDate($column, '<=', $filters["{$prefix}_to"]);
            }
        }
    }
}
