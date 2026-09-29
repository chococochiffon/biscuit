<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * フォームに埋め込んだ繰り返し入力(並び順を持つ行)の内容を、データベースへ同期するコントローラー用の共通処理。
 */
trait SyncsSortableRows
{
    /**
     * 送信された行の内容に、クエリが対象とする行を同期する。
     * 送信された行は id の有無で作成/更新し、送信されなかった既存行は削除(論理削除)する。
     * 並び順(sort_order)は画面上の行の順(JS が設定する。未送信の場合は行のキー(入力名の番号)の順)で保存する。
     *
     * 親に属する行だけを同期する場合は、親のリレーション(例: $singlePage->details())を渡す。
     * 他の親の行は削除・更新の対象にならず、作成時は親の外部キーが設定される。
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>|HasMany<TModel, covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<int|string, array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): array<string, mixed>  $attributes  行から保存する属性(sort_order 以外)を組み立てる
     * @return array<int|string, int> 送信された行のキー → 保存した行の id(行に属する子の行を続けて同期するときに使う)
     */
    protected function syncSortableRows(Builder|HasMany $query, array $rows, callable $attributes): array
    {
        // validated() は行をルールの順に組み立て直すため(id のある行が先になるなど)、行のキーの順に並べ直す
        ksort($rows);
        $submittedIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        (clone $query)->whereNotIn('id', $submittedIds)->delete();

        $savedIds = [];

        foreach (array_keys($rows) as $index => $key) {
            $row = $rows[$key];
            $values = $attributes($row) + ['sort_order' => $row['sort_order'] ?? $index];

            if (! empty($row['id'])) {
                (clone $query)->whereKey($row['id'])->update($values);
                $savedIds[$key] = (int) $row['id'];
            } else {
                $savedIds[$key] = (clone $query)->create($values)->getKey();
            }
        }

        return $savedIds;
    }
}
