<?php

namespace App\Models;

use App\Notifications\LoginCodeNotification;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 二段階認証の確認コード。管理者(Administrator)・ユーザー(User)のログインで、パスワードが正しいときに issue() でメールを送り、
 * verify() でコードを確かめる。どのログインへのコードかはチャレンジで見分ける(管理画面はセッション、chococo はサーバーの Cookie に持つ)。
 * 有効期限は config('auth.login_codes.expire')(分)で、config('auth.login_codes.max_attempts') 回間違えると使えなくなる。
 * 使った・送り直したコードは削除せず、used_at・expires_at で使えなくする。
 */
#[Fillable(['challenge', 'code', 'expires_at', 'attempts', 'used_at'])]
#[Hidden(['challenge', 'code'])]
class LoginCode extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * 確認コードを発行してメールで送り、チャレンジ(平文)を返す。同じアカウントのまだ使えるコードは使えなくする。
     */
    public static function issue(Administrator|User $account): string
    {
        self::query()
            ->whereMorphedTo('authenticatable', $account)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        $challenge = Str::random(64);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $loginCode = new self([
            'challenge' => self::hashChallenge($challenge),
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('auth.login_codes.expire')),
        ]);
        $loginCode->authenticatable()->associate($account);
        $loginCode->save();

        $account->notify(new LoginCodeNotification($code));

        return $challenge;
    }

    /**
     * チャレンジと入力されたコードを確かめ、正しければコードを使用済みにしてアカウントを返す。
     * 間違えたら回数を数え、上限に達したコードは使えなくなる。$type はアカウントの種類(管理者・ユーザー)。
     *
     * @param  class-string<Administrator|User>  $type
     */
    public static function verify(string $type, string $challenge, string $code): Administrator|User|null
    {
        $loginCode = self::usable($type, $challenge);

        if ($loginCode === null) {
            return null;
        }

        if (! Hash::check($code, $loginCode->code)) {
            $loginCode->increment('attempts');

            return null;
        }

        $loginCode->update(['used_at' => now()]);

        return $loginCode->authenticatable;
    }

    /**
     * チャレンジのアカウントに、確認コードを送り直して新しいチャレンジを返す(チャレンジがもう使えなければ null)。
     * 期限が切れた・間違えすぎたコードでも、使用済みでなければ送り直せる。
     *
     * @param  class-string<Administrator|User>  $type
     */
    public static function resend(string $type, string $challenge): ?string
    {
        $loginCode = self::query()
            ->where('challenge', self::hashChallenge($challenge))
            ->where('authenticatable_type', (new $type)->getMorphClass())
            ->whereNull('used_at')
            ->first();

        return $loginCode?->authenticatable ? self::issue($loginCode->authenticatable) : null;
    }

    /**
     * まだ使える(使用前・有効期限内・間違えた回数が上限未満の)コード。
     *
     * @param  class-string<Administrator|User>  $type
     */
    private static function usable(string $type, string $challenge): ?self
    {
        return self::query()
            ->where('challenge', self::hashChallenge($challenge))
            ->where('authenticatable_type', (new $type)->getMorphClass())
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', (int) config('auth.login_codes.max_attempts'))
            ->first();
    }

    private static function hashChallenge(string $challenge): string
    {
        return hash('sha256', $challenge);
    }

    /**
     * コードを送ったアカウント(管理者・ユーザー)。
     *
     * @return MorphTo<Authenticatable, $this>
     */
    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
