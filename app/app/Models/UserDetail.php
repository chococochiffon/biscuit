<?php

namespace App\Models;

use App\Enums\UserDetailNameSetting;
use App\Models\Concerns\HasPublicImages;
use Database\Factories\UserDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;

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
    use HasFactory, HasPublicImages, SoftDeletes;

    /**
     * アイコン画像(user_image)の保存先ディレクトリ(公開ディスク基準)。
     */
    public const USER_IMAGE_DIRECTORY = 'image/user';

    /**
     * アイコン画像の保存サイズ(正方形の一辺)。切り抜いた範囲をこのサイズへ拡大・縮小する。
     */
    public const USER_IMAGE_SIZE = 250;

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
            'name_settings' => UserDetailNameSetting::class,
        ];
    }

    /**
     * 詳細が紐づくユーザーを取得する。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * このユーザー詳細に紐づくスキルを並び順(sort_order、同順ならid)で取得する。
     */
    public function skills(): HasMany
    {
        return $this->hasMany(UserSkill::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * アイコン画像を指定範囲(未指定なら中央)で正方形に切り抜いて規定サイズで保存し、公開ディスク基準の保存パスを返す。
     *
     * @param  array{x: int|float, y: int|float, width: int|float, height: int|float}|null  $crop
     */
    public function storeUserImage(UploadedFile $file, ?array $crop = null): string
    {
        return $this->storeNamedImage($file, self::USER_IMAGE_DIRECTORY, [self::USER_IMAGE_SIZE, self::USER_IMAGE_SIZE], $crop);
    }

    /**
     * アイコン画像の公開URL(未設定なら null)。
     *
     * @return Attribute<string|null, never>
     */
    protected function userImageUrl(): Attribute
    {
        return Attribute::get(fn () => self::publicImageUrl($this->user_image));
    }
}
