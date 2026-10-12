<?php

namespace App\Support\TwoFactor;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * chococo のマイページのログインで、認証アプリ(TOTP)のコードを待っているチャレンジ。
 * API は Cookie のセッションを使わないため、チャレンジ(平文は chococo のサーバーの Cookie に入る)の SHA-256 をキーにして
 * ユーザーの id と間違えた回数をキャッシュに置く。有効期限はメールの確認コードと同じ config('auth.login_codes.expire')(分)で、
 * config('auth.login_codes.max_attempts') 回間違えると使えなくなる。
 */
class TotpChallenge
{
    private const PREFIX = 'two_factor_challenge:';

    /**
     * ユーザーのチャレンジを作り、平文を返す。
     */
    public static function issue(User $user): string
    {
        $challenge = Str::random(64);
        Cache::put(self::key($challenge), ['user_id' => $user->id, 'attempts' => 0], now()->addMinutes((int) config('auth.login_codes.expire')));

        return $challenge;
    }

    /**
     * チャレンジのユーザー(チャレンジがない・期限切れ・間違えすぎなら null)。
     */
    public static function user(string $challenge): ?User
    {
        $pending = Cache::get(self::key($challenge));

        return is_array($pending) ? User::query()->find($pending['user_id']) : null;
    }

    /**
     * 間違えた回数を数える。上限に達したらチャレンジを消して false を返す(ログインからやり直してもらう)。
     */
    public static function failed(string $challenge): bool
    {
        $pending = Cache::get(self::key($challenge));

        if (! is_array($pending)) {
            return false;
        }

        $pending['attempts']++;

        if ($pending['attempts'] >= (int) config('auth.login_codes.max_attempts')) {
            self::forget($challenge);

            return false;
        }

        Cache::put(self::key($challenge), $pending, now()->addMinutes((int) config('auth.login_codes.expire')));

        return true;
    }

    public static function forget(string $challenge): void
    {
        Cache::forget(self::key($challenge));
    }

    private static function key(string $challenge): string
    {
        return self::PREFIX.hash('sha256', $challenge);
    }
}
