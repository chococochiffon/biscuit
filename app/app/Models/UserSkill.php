<?php

namespace App\Models;

use App\Models\Concerns\HasSortOrder;
use Database\Factories\UserSkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_detail_id', 'name', 'level', 'sort_order'])]
class UserSkill extends Model
{
    /** @use HasFactory<UserSkillFactory> */
    use HasFactory, HasSortOrder, SoftDeletes;

    /**
     * 習熟度(level)の下限。
     */
    public const MIN_LEVEL = 1;

    /**
     * 習熟度(level)の上限。MIN_LEVEL〜この値の 5 段階で入力する。
     */
    public const MAX_LEVEL = 5;

    /**
     * スキルが紐づくユーザー詳細を取得する。
     */
    public function userDetail(): BelongsTo
    {
        return $this->belongsTo(UserDetail::class);
    }
}
