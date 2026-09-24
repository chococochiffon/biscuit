<?php

namespace App\Models;

use App\Enums\UserDetailNameSetting;
use Database\Factories\UserDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
     * ユーザー画像の保存先ディレクトリ(公開ディスク基準)。
     */
    public const USER_IMAGE_DIRECTORY = 'image/user';

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
     * ユーザー画像を保存し、公開ディスク基準の保存パスを返す。
     */
    public function storeUserImage(UploadedFile $file): string
    {
        Storage::disk('public')->makeDirectory(self::USER_IMAGE_DIRECTORY);

        $filename = now()->format('YmdHis').'_'.$this->getTable().'_'.$this->id.'.'.$file->extension();

        return $file->storeAs(self::USER_IMAGE_DIRECTORY, $filename, 'public');
    }
}
