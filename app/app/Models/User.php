<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'skip_approval', 'active_flag'])]
#[Hidden(['password', 'remember_token', 'unique_email'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * 公開側(chococo)の投稿者ページの URL の先頭(/authors/{id})。記事・固定ページ・カスタムページの URL の先頭には使えない。
     */
    public const AUTHOR_PATH_PREFIX = 'authors';

    /**
     * 作成直後(DB から読み直す前)も既定値を持たせる。
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'skip_approval' => false,
        'active_flag' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'skip_approval' => 'boolean',
            'active_flag' => 'boolean',
        ];
    }

    /**
     * 有効期限内の API トークン(chococo のマイページのログイン)をすべて無効にする。$except のトークンは残す。
     * トークンは削除せず、有効期限を切らして無効にする(物理削除しない方針のため)。
     */
    public function expireTokens(?int $except = null): void
    {
        $this->tokens()
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->update(['expires_at' => now()]);
    }

    /**
     * ユーザーに紐づく詳細情報を取得する。
     */
    public function detail(): HasOne
    {
        return $this->hasOne(UserDetail::class);
    }

    /**
     * ユーザーが投稿した記事を取得する(chococo のマイページの記事管理)。
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /**
     * 招待のメールアドレスから、アカウント名の初期値(@ の前)を作る。
     */
    public static function accountNameFromEmail(string $email): string
    {
        return Str::before($email, '@');
    }

    /**
     * 管理者からの招待(再送するたびに増える)。
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
    }

    /**
     * いちばん新しい招待(管理画面のユーザー一覧で、招待中か期限切れかを出す)。
     */
    public function latestInvitation(): HasOne
    {
        return $this->hasOne(UserInvitation::class)->latestOfMany();
    }

    /**
     * ユーザーがマイページから投稿したギャラリーの画像を取得する。
     */
    public function galleryImages(): HasMany
    {
        return $this->hasMany(GalleryImage::class);
    }

    /**
     * パスワード再設定のメールを、公開側(chococo)の再設定ページへのリンクで送る。
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * 公開側に出す投稿者名。ユーザー詳細の名前の表示設定に従い、非表示・未登録なら「投稿者」にする(アカウント名は出さない)。
     */
    public function authorName(): string
    {
        return $this->detail?->displayName() ?? __('投稿者');
    }

    /**
     * 投稿者ページを公開しているか(ユーザー詳細の「プロフィールを公開する」がオンで、論理削除されていない)。
     */
    public function hasPublicProfile(): bool
    {
        return ! $this->trashed() && $this->detail?->view_flag === true;
    }

    /**
     * 投稿者ページの URL(公開していなければ null)。
     */
    public function authorProfilePath(): ?string
    {
        return $this->hasPublicProfile() ? '/'.self::AUTHOR_PATH_PREFIX.'/'.$this->id : null;
    }
}
