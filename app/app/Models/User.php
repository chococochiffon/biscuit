<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'unique_email'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

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
}
