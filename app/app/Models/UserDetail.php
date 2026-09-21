<?php

namespace App\Models;

use Database\Factories\UserDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'first_name',
    'family_name',
    'nick_name',
    'birthday',
    'user_image',
    'comment',
    'view_flag',
    'name_settings',
])]
class UserDetail extends Model
{
    /** @use HasFactory<UserDetailFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'view_flag' => 'boolean',
            'name_settings' => 'integer',
        ];
    }

    /**
     * 詳細が紐づくユーザーを取得する。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
