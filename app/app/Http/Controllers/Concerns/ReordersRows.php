<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\AuditAction;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * 一覧・管理モーダルの行のドラッグ並び替え(order[] に画面上の順の id を送る)を保存する共通処理。
 */
trait ReordersRows
{
    /**
     * 送られた順(order[])に並び順(sort_order)を保存し、並び替えを監査ログに記録する。
     * $paginated が true のときは、ページネーションした一覧の途中のページとして offset(そのページの先頭の並び順)と page を受け付ける。
     *
     * @param  class-string<Model>  $model
     * @return array{order: list<int>, offset: int, page: int|null}
     */
    protected function saveReorder(Request $request, string $model, bool $paginated = false): array
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', Rule::exists((new $model)->getTable(), 'id')->withoutTrashed()],
            ...($paginated ? [
                'offset' => ['nullable', 'integer', 'min:0'],
                'page' => ['nullable', 'integer', 'min:1'],
            ] : []),
        ]);

        $order = array_map('intval', array_values($validated['order']));
        $offset = (int) ($validated['offset'] ?? 0);

        // 途中で失敗しても並び順が中途半端にならないよう、まとめて保存する
        DB::transaction(function () use ($model, $order, $offset, $paginated) {
            foreach ($order as $index => $id) {
                $model::query()->whereKey($id)->update(['sort_order' => $offset + $index]);
            }

            AuditLogger::record(AuditAction::Reordered, Str::snake(class_basename($model)), metadata: $paginated
                ? ['order' => $order, 'offset' => $offset]
                : ['order' => $order]);
        });

        return ['order' => $order, 'offset' => $offset, 'page' => isset($validated['page']) ? (int) $validated['page'] : null];
    }
}
