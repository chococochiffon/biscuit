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
     * このインスタンスのテーブルの種類(Eloquent が作るインスタンスにも newInstance() で引き継ぐ)。
     */
    protected ?CustomPageType $customPageType = null;

    /**
     * 指定した種類のテーブルを設定したインスタンスを返す。
     */
    public static function forType(CustomPageType $type): static
    {
        $model = new static;
        $model->customPageType = $type;
        $model->setTable(static::tableNameFor($type));
        $model->mergeCasts(static::castsFor($type));

        return $model;
    }

    /**
     * このインスタンスのテーブルの種類。
     */
    public function customPageType(): CustomPageType
    {
        return $this->customPageType;
    }

    /**
     * Eloquent が取得・作成するインスタンスにも、種類を引き継ぐ(テーブルとキャストは Eloquent が引き継ぐ)。
     *
     * @param  array<string, mixed>  $attributes
     * @param  bool  $exists
     */
    public function newInstance($attributes = [], $exists = false): static
    {
        $model = parent::newInstance($attributes, $exists);
        $model->customPageType = $this->customPageType;

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
