<?php

namespace App\Models\CustomPages;

use App\Models\Concerns\BelongsToCustomPageType;
use App\Models\CustomPageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * カスタムフォームの入力値(customs_○○_form_values の 1 行)。value は入力値を JSON で保存する
 * (チェックボックスは選んだ選択肢の配列、それ以外は文字列)。外部キーの列名は種類ごとに違う。
 */
class CustomFormValue extends Model
{
    use BelongsToCustomPageType, SoftDeletes;

    /**
     * 一括代入の制限をかけない(フォームリクエストで検証した値だけを渡す)。
     * $guarded に列を並べると、Laravel が列の一覧をモデルのクラスごとにキャッシュし、
     * テーブルが種類ごとに違うこのモデルでは別の種類の列が捨てられてしまうため。
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    protected static function tableNameFor(CustomPageType $type): string
    {
        return $type->formValuesTableName();
    }
}
