<?php

namespace App\Models\CustomPages;

use App\Enums\ArticleApprovalStatus;
use App\Enums\CustomPageBaseType;
use App\Models\Concerns\BelongsToCustomPageType;
use App\Models\CustomPageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * カスタムページの本体(user_make_○○ の 1 行)。列は種類のベースの型(記事/固定ページ)で違う。
 */
class CustomPageEntry extends Model
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

    protected static function tableNameFor(CustomPageType $type): string
    {
        return $type->tableName();
    }

    /**
     * @return array<string, string>
     */
    protected static function castsFor(CustomPageType $type): array
    {
        return [
            'publication_start_datetime' => 'datetime',
            'publication_end_datetime' => 'datetime',
            ...($type->base_type === CustomPageBaseType::Article ? ['approval' => ArticleApprovalStatus::class] : []),
        ];
    }
}
