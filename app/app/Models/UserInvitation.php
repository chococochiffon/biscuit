<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * 管理者からユーザーへの招待。メールのリンク(公開側 chococo の /invitation?token=…&email=…)で、招待されたユーザーが
 * プロフィールとパスワードを登録すると受諾(accepted_at)になり、ユーザーが有効(users.active_flag)になる。
 * トークンは SHA-256 のハッシュで持つ。再送で新しい招待を出すと、それまでの招待は有効期限を切らして無効にする(削除しない)。
 */
#[Fillable(['user_id', 'administrator_id', 'token', 'expires_at', 'accepted_at'])]
#[Hidden(['token'])]
class UserInvitation extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * ユーザーへの招待を新しく出し、メールのリンクに入れるトークン(平文)を返す。まだ使える古い招待は無効にする。
     */
    public static function issue(User $user, ?Administrator $invitedBy): string
    {
        $user->invitations()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        $token = Str::random(64);

        $user->invitations()->create([
            'administrator_id' => $invitedBy?->id,
            'token' => self::hashToken($token),
            'expires_at' => now()->addMinutes((int) config('auth.invitations.expire')),
        ]);

        return $token;
    }

    /**
     * メールのリンクのトークンとメールアドレスから、まだ使える(受諾前・有効期限内で、ユーザーがまだ無効な)招待を探す。
     */
    public static function findUsable(string $email, string $token): ?self
    {
        return self::query()
            ->with('user')
            ->where('token', self::hashToken($token))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->whereHas('user', fn ($query) => $query->where('email', $email)->where('active_flag', false))
            ->first();
    }

    private static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * 招待されたユーザー。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 招待した管理者(null なら不明)。
     */
    public function administrator(): BelongsTo
    {
        return $this->belongsTo(Administrator::class)->withTrashed();
    }

    /**
     * 有効期限が切れているか(受諾済みは除く)。
     */
    public function isExpired(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isPast();
    }
}
