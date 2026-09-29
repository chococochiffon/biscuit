<?php

namespace App\Support;

/**
 * 繰り返し入力の行を同期した結果(SyncsSortableRows::syncSortableRows() の戻り値)。
 */
class SyncedRows
{
    /**
     * @param  array<int|string, int>  $ids  送信された行のキー → 保存した行の id(行に属する子の行を続けて同期するときに使う)
     */
    public function __construct(
        public readonly array $ids,
        public readonly int $created,
        public readonly int $updated,
        public readonly int $deleted,
    ) {}

    /**
     * 監査ログの補足(metadata)に残す、作成・更新・削除した行の件数。
     *
     * @return array{created: int, updated: int, deleted: int}
     */
    public function summary(): array
    {
        return ['created' => $this->created, 'updated' => $this->updated, 'deleted' => $this->deleted];
    }
}
