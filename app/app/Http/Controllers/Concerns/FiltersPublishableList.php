<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * タイトルと公開期間(HasPublicationPeriod)を持つモデル(記事・固定ページ)の管理画面一覧で、
 * 検索条件と並び順を扱うコントローラー用の共通処理。
 */
trait FiltersPublishableList
{
    /**
     * 一覧のデフォルトの並び順(更新日時の新しい順)。
     */
    private const DEFAULT_SORT = 'updated_at_desc';

    /**
     * 一覧で選択可能な並び順(キー → 並び替えるカラム・方向)。一覧の見出しクリックで「項目_asc/desc」のキーが送られる。
     * 共通の並び順(commonListSortOptions())に、コントローラー独自の並び順を足して返す。
     *
     * @return array<string, array{column: string, direction: string}>
     */
    abstract protected function listSortOptions(): array;

    /**
     * リクエストのクエリから一覧の検索条件と並び順を取り出す。
     * 不正な検索条件でリダイレクトを繰り返さないよう、妥当な値だけを採用して残りは無視する。
     * タイトル(部分一致)・公開開始/公開終了(日付の範囲)・並び順は共通で、それ以外の条件は $extraRules で足す。
     *
     * @param  array<string, array<mixed>>  $extraRules
     * @return array{0: array<string, mixed>, 1: string, 2: bool} [検索条件, 並び順のキー, 検索条件(並び順以外)が指定されているか]
     */
    protected function listFilters(Request $request, array $extraRules = []): array
    {
        $filters = Validator::make($request->query(), [
            'title' => ['nullable', 'string', 'max:255'],
            'publication_start_from' => ['nullable', 'date_format:Y-m-d'],
            'publication_start_to' => ['nullable', 'date_format:Y-m-d'],
            'publication_end_from' => ['nullable', 'date_format:Y-m-d'],
            'publication_end_to' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', Rule::in(array_keys($this->listSortOptions()))],
            ...$extraRules,
        ])->valid();

        $sort = $filters['sort'] ?? self::DEFAULT_SORT;
        $isSearching = collect($filters)->except('sort')->filter(fn ($value) => filled($value))->isNotEmpty();

        return [$filters, $sort, $isSearching];
    }

    /**
     * タイトル(部分一致)・公開期間で絞り込み、選択した並び順(同順は id)で並べる。
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @param  array<string, mixed>  $filters
     * @return TBuilder
     */
    protected function applyListFilters(Builder $query, array $filters, string $sort): Builder
    {
        ['column' => $column, 'direction' => $direction] = $this->listSortOptions()[$sort];

        return $query
            ->when(filled($filters['title'] ?? null), fn (Builder $query) => $query->where('title', 'like', '%'.$filters['title'].'%'))
            ->filterPublicationPeriod($filters)
            ->orderBy($column, $direction)
            ->orderBy('id', $direction);
    }

    /**
     * 記事・固定ページで共通の並び順(更新日時・タイトル・公開開始・公開終了)。
     *
     * @return array<string, array{column: string, direction: string}>
     */
    protected function commonListSortOptions(): array
    {
        return [
            'updated_at_desc' => ['column' => 'updated_at', 'direction' => 'desc'],
            'updated_at_asc' => ['column' => 'updated_at', 'direction' => 'asc'],
            'title_asc' => ['column' => 'title', 'direction' => 'asc'],
            'title_desc' => ['column' => 'title', 'direction' => 'desc'],
            'publication_start_desc' => ['column' => 'publication_start_datetime', 'direction' => 'desc'],
            'publication_start_asc' => ['column' => 'publication_start_datetime', 'direction' => 'asc'],
            'publication_end_desc' => ['column' => 'publication_end_datetime', 'direction' => 'desc'],
            'publication_end_asc' => ['column' => 'publication_end_datetime', 'direction' => 'asc'],
        ];
    }
}
