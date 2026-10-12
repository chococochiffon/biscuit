<?php

namespace App\Models\Concerns;

use App\Enums\TwoFactorMethod;
use App\Support\TwoFactor\Totp;

/**
 * 二段階認証の方法(メールの確認コードか認証アプリ(TOTP))を持つアカウント(管理者・ユーザー)。
 * 使うモデルは two_factor_method を TwoFactorMethod に、two_factor_secret を encrypted にキャストし、two_factor_secret を $hidden に入れる。
 */
trait HasTwoFactor
{
    /**
     * 認証アプリで二段階認証をするか。
     */
    public function usesTotp(): bool
    {
        return $this->two_factor_method === TwoFactorMethod::Totp && filled($this->two_factor_secret);
    }

    /**
     * 認証アプリのコードを確かめる。正しければ使ったコードの時刻を保存し(同じコードを 2 回使わせない)、true を返す。
     */
    public function verifyTotp(string $code): bool
    {
        if (! $this->usesTotp()) {
            return false;
        }

        $step = app(Totp::class)->verify($this->two_factor_secret, $code, $this->two_factor_last_used_step);

        if ($step === null) {
            return false;
        }

        $this->forceFill(['two_factor_last_used_step' => $step])->save();

        return true;
    }

    /**
     * 認証アプリを使うようにする($step は設定のときに確かめたコードの時刻)。
     */
    public function enableTotp(string $secret, int $step): void
    {
        $this->forceFill([
            'two_factor_method' => TwoFactorMethod::Totp,
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_step' => $step,
        ])->save();
    }

    /**
     * 認証アプリをやめて、メールの確認コードに戻す。
     */
    public function disableTotp(): void
    {
        $this->forceFill([
            'two_factor_method' => TwoFactorMethod::Email,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
        ])->save();
    }
}
