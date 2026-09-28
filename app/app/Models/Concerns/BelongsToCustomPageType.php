<?php

namespace App\Models\Concerns;

use App\Models\CustomPageType;
use Illuminate\Database\Eloquent\Builder;

/**
 * カスタムページの種類ごとのテーブル(user_make_○○ など)を扱うモデル用の共通処理。
 * テーブルが種類ごとに違うため、forType() で種類のテーブルを設定したインスタンスから問い合わせる
 * (Eloquent が取得・作成するインスタンスには、テーブルとキャストが引き継がれる)。
 */
trait BelongsToCustomPageType
{
    /**
     * 指定した種類のテーブルを設定したインスタンスを返す。
     */
    public static function forType(CustomPageType $type): static
    {
        $model = new static;
        $model->setTable(static::tableNameFor($type));
        $model->mergeCasts(static::castsFor($type));

        return $model;
    }

    /**
     * 指定した種類のテーブルへのクエリ。
     *
     * @return Builder<static>
     */
    public static function queryFor(CustomPageType $type): Builder
    {
        return static::forType($type)->newQuery();
    }

    /**
     * 種類ごとのテーブル名。
     */
    abstract protected static function tableNameFor(CustomPageType $type): string;

    /**
     * 種類ごとのキャスト(型によって列が違う場合に使う)。
     *
     * @return array<string, string>
     */
    protected static function castsFor(CustomPageType $type): array
    {
        return [];
    }
}
