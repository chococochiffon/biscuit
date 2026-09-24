<?php

namespace App\Models;

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
    use HasFactory, SoftDeletes;

    /**
     * 習熟度(level)の上限。0〜この値の範囲で入力する。
     */
    public const MAX_LEVEL = 100;

    /**
     * スキルが紐づくユーザー詳細を取得する。
     */
    public function userDetail(): BelongsTo
    {
        return $this->belongsTo(UserDetail::class);
    }
}
