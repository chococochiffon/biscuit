<?php

namespace App\Models\Concerns;

use Carbon\CarbonInterface;
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

    /**
     * 指定日時(省略時は現在)に公開期間内のものに絞り込む。
     * 公開開始 <= 指定日時 かつ(公開終了が未設定 または 指定日時 < 公開終了)を公開期間内とする。
     */
    #[Scope]
    protected function withinPublicationPeriod(Builder $query, ?CarbonInterface $at = null): void
    {
        $at ??= now();

        $query->where('publication_start_datetime', '<=', $at)
            ->where(fn (Builder $query) => $query
                ->whereNull('publication_end_datetime')
                ->orWhere('publication_end_datetime', '>', $at));
    }
}
