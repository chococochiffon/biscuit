<?php

namespace App\Installer;

use InvalidArgumentException;
use RuntimeException;

/**
 * インストーラーが .env を書き換える。書き換えられるのは ALLOWED_KEYS のキーだけで、ほかの行はそのまま残す
 * (ブラウザから .env 全体を編集させない)。値はシングルクォートで囲み、.env と Docker Compose で特別な意味を持つ文字は受け付けない。
 */
class EnvironmentWriter
{
    /**
     * インストーラーが書き換えてよいキー。
     *
     * @var list<string>
     */
    public const ALLOWED_KEYS = ['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_ROOT_PASSWORD'];

    /**
     * 値に使えない文字(シングルクォートで囲んでも .env・Docker Compose で壊れる・展開されるもの)と改行。
     */
    public const UNSAFE_VALUE_PATTERN = '/[\'"\\\\$`#\s]/';

    public function __construct(private ?string $path = null) {}

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

        // 途中で失敗しても .env が半端にならないよう、一時ファイルに書いてから置き換える
        $temporary = $path.'.installer.tmp';

        if (file_put_contents($temporary, $content) === false || ! rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException('Failed to write .env.');
        }
    }
}
