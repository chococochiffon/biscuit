<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * フォームに埋め込んだ繰り返し入力(並び順を持つ行)の表示用の行を組み立てる。
 */
class RepeaterRows
{
    /**
     * 入力エラーで戻った場合は old($oldKey) の入力値から、それ以外は保存済みのモデルから行を組み立てる。
     * 各行には共通で index(入力名の番号。0 から振り直す)・id・sortOrder(入力値に並び順がなければ行の順)を付け、
     * それ以外の項目は $fromInput(入力値の 1 行から)/$fromModel(モデルから)で組み立てる。
     *
     * @template TModel of Model
     *
     * @param  iterable<TModel>  $models
     * @param  callable(array<string, mixed>): array<string, mixed>  $fromInput
     * @param  callable(TModel): array<string, mixed>  $fromModel
     * @return Collection<int, object>
     */
    public static function build(string $oldKey, iterable $models, callable $fromInput, callable $fromModel): Collection
    {
        $oldRows = old($oldKey);

        return $oldRows !== null ? self::fromInput($oldRows, $fromInput) : self::fromModels($models, $fromModel);
    }

    /**
     * 入力値の行(入力エラーで戻った値など)から表示用の行を組み立てる。部品の中の行など、入れ子の繰り返し入力で使う。
     *
     * @param  array<int|string, array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): array<string, mixed>  $fromInput
     * @return Collection<int, object>
     */
    public static function fromInput(array $rows, callable $fromInput): Collection
    {
        return self::withIndex(collect($rows)->values()->map(fn (array $row, int $index) => [
            'id' => $row['id'] ?? null,
            'sortOrder' => $row['sort_order'] ?? $index,
            ...$fromInput($row),
        ]));
    }

    /**
     * 保存済みのモデルから表示用の行を組み立てる。部品の中の行など、入れ子の繰り返し入力で使う。
     *
     * @template TModel of Model
     *
     * @param  iterable<TModel>  $models
     * @param  callable(TModel): array<string, mixed>  $fromModel
     * @return Collection<int, object>
     */
    public static function fromModels(iterable $models, callable $fromModel): Collection
    {
        return self::withIndex(collect($models)->values()->map(fn (Model $model) => [
            'id' => $model->getKey(),
            'sortOrder' => $model->getAttribute('sort_order'),
            ...$fromModel($model),
        ]));
    }

    /**
     * 各行に入力名の番号(index。0 から振り直す)を付ける。
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, object>
     */
    private static function withIndex(Collection $rows): Collection
    {
        return $rows->map(fn (array $row, int $index) => (object) ['index' => (string) $index, ...$row]);
    }

    /**
     * 選択欄の入力値を整数に変換する(未選択・空なら null)。
     */
    public static function intOrNull(mixed $value): ?int
    {
        return filled($value) ? (int) $value : null;
    }
}
