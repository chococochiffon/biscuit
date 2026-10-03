<?php

namespace App\Installer;

use InvalidArgumentException;
use RuntimeException;

/**
 * インストーラーが .env を書き換える。書き換えられるのは ALLOWED_KEYS のキーだけで、ほかの行はそのまま残す
 * (ブラウザから .env 全体を編集させない)。値はシングルクォートで囲み、シングルクォートと改行などの制御文字は受け付けない。
 */
class EnvironmentWriter
{
    /**
     * インストーラーが書き換えてよいキー。
     *
     * @var list<string>
     */
    public const ALLOWED_KEYS = [
        // データベース(データベースの段)
        'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_ROOT_PASSWORD',
        // 本番向けの初期値(biscuit:install --prepare)と URL・ポート(本番用の Compose も読む)
        'APP_ENV', 'APP_DEBUG', 'APP_URL', 'FRONT_URL', 'LOG_LEVEL', 'PAGE_VIEW_FORWARD_KEY', 'BISCUIT_ADMIN_PORT', 'BISCUIT_FRONT_PORT',
        // サイトの段(言語・タイムゾーン)
        'APP_LOCALE', 'APP_TIMEZONE',
        // メールの段(SMTP)
        'MAIL_MAILER', 'MAIL_SCHEME', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS',
    ];

    /**
     * 値に使えない文字。値はシングルクォートで囲む(.env・Docker Compose のどちらでも中身はそのまま読まれる)ため、
     * 壊れるのはシングルクォートと改行などの制御文字だけ(DB のパスワードは、さらに DatabaseRequest で記号を絞る)。
     */
    public const UNSAFE_VALUE_PATTERN = '/[\'\x00-\x1F\x7F]/';

    public function __construct(private ?string $path = null) {}

    /**
     * キーの今の値(クォートを外す。なければ null)。
     */
    public function get(string $key): ?string
    {
        $path = $this->path ?? base_path('.env');
        $content = is_file($path) ? (string) file_get_contents($path) : '';

        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $content, $matches) !== 1) {
            return null;
        }

        return preg_replace('/\A([\'"])(.*)\1\z/', '$2', trim($matches[1]));
    }

    /**
     * キーの値を書き換える(なければ末尾に足す)。
     *
     * @param  array<string, string|int>  $values
     */
    public function set(array $values): void
    {
        $path = $this->path ?? base_path('.env');
        $content = is_file($path) ? (string) file_get_contents($path) : '';

        foreach ($values as $key => $value) {
            if (! in_array($key, self::ALLOWED_KEYS, true)) {
                throw new InvalidArgumentException("The installer cannot change {$key}.");
            }

            $value = (string) $value;

            if (preg_match(self::UNSAFE_VALUE_PATTERN, $value) === 1) {
                throw new InvalidArgumentException("The value of {$key} contains characters that cannot be written to .env.");
            }

            $line = "{$key}='{$value}'";
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $content = preg_match($pattern, $content) === 1
                ? (string) preg_replace_callback($pattern, fn () => $line, $content, 1)
                : rtrim($content, "\n")."\n{$line}\n";
        }

        // 本番では php-fpm(www-data)が app/ のディレクトリに書き込めないため、一時ファイルを置き換えずに .env へ直接書く(書き込み中はロックする)
        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new RuntimeException('Failed to write .env.');
        }
    }
}
