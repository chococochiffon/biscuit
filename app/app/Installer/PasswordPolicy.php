<?php

namespace App\Installer;

use Illuminate\Support\Str;

/**
 * インストーラーのパスワードのポリシー(config/installer.php)と、安全なパスワードの生成。
 */
class PasswordPolicy
{
    /**
     * 生成するパスワードに使う文字(DatabaseRequest::UNSAFE_PASSWORD_PATTERN の文字を含まない)。
     */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789-_.!%+=';

    /**
     * 配布物の初期値・よく使われる値か(大文字小文字を問わない)。
     */
    public static function isForbidden(string $password): bool
    {
        return in_array(Str::lower($password), array_map(Str::lower(...), config('installer.forbidden_passwords')), true);
    }

    /**
     * 予測しにくいパスワードを生成する(英大文字・英小文字・数字・記号を少なくとも 1 文字ずつ含む)。
     */
    public static function generate(?int $length = null): string
    {
        $length ??= (int) config('installer.generated_password_length');
        $alphabet = str_split(self::ALPHABET);

        do {
            $password = implode('', array_map(fn () => $alphabet[random_int(0, count($alphabet) - 1)], range(1, $length)));
        } while (! (preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password) && preg_match('/\d/', $password) && preg_match('/[-_.!%+=]/', $password)));

        return $password;
    }
}
