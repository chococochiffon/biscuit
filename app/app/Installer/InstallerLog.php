<?php

namespace App\Installer;

use Illuminate\Support\Facades\Log;

/**
 * インストーラー専用のログ(storage/logs/installer.log)。パスワード・秘密の値・APP_KEY は、文脈に入れても伏せて書く。
 */
class InstallerLog
{
    /**
     * 伏せて書く文脈のキー(大文字小文字を問わず、この語を含むもの)。
     */
    private const SECRET_KEY_PATTERN = '/password|secret|key|token/i';

    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $message, array $context = []): void
    {
        Log::channel('installer')->info($message, self::redact($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function error(string $message, array $context = []): void
    {
        Log::channel('installer')->error($message, self::redact($context));
    }

    /**
     * 秘密の値を伏せた文脈。
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_KEY_PATTERN, $key) === 1) {
                $context[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $context[$key] = self::redact($value);
            }
        }

        return $context;
    }
}
