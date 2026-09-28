<?php

namespace App\Models\CustomPages;

use App\Models\Concerns\BelongsToCustomPageType;
use App\Models\Concerns\HasSortOrder;
use App\Models\CustomPageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 固定ページ型のカスタムページの詳細(user_make_○○_details の 1 行)。
 */
class CustomPageDetail extends Model
{
    use BelongsToCustomPageType, HasSortOrder, SoftDeletes;

    /**
     * 一括代入の制限をかけない(フォームリクエストで検証した値だけを渡す)。
     * $guarded に列を並べると、Laravel が列の一覧をモデルのクラスごとにキャッシュし、
     * テーブルが種類ごとに違うこのモデルでは別の種類の列が捨てられてしまうため。
     *
     * @var list<string>
     */
    protected $guarded = [];

    protected static function tableNameFor(CustomPageType $type): string
    {
        return $type->detailsTableName();
    }
}
