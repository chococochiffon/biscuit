<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * メールの送り方(SMTP か Resend)の設定から、Laravel のメーラーの設定を作り、試しのメールを送る。
 * インストーラーのメールの段(App\Installer\MailInstaller)と、管理画面のメールの設定(MailSettingController)で使う。
 * 失敗したときの文言からは、SMTP のパスワード・Resend の API キーを伏せる。
 */
class MailTransport
{
    /**
     * 選べる送り方(MAIL_MAILER の値)。
     *
     * @var list<string>
     */
    public const DRIVERS = ['smtp', 'resend'];

    /**
     * 選べる SMTP の暗号化(starttls は smtp のまま、つながったあとに使えれば STARTTLS にする)。
     *
     * @var list<string>
     */
    public const ENCRYPTIONS = ['starttls', 'ssl'];

    /**
     * 試しのメールを送るときだけ使うメーラーの名前。
     */
    private const TEST_MAILER = 'mail_transport_test';

    /**
     * 送り方の設定から、メーラーの設定(config('mail.mailers.*') の形)を作る。
     *
     * @param  array{driver: string, host?: string|null, port?: int|string|null, encryption?: string|null, username?: string|null, password?: string|null, api_key?: string|null}  $values
     * @return array<string, mixed>
     */
    public static function mailerConfig(array $values): array
    {
        return $values['driver'] === 'resend'
            ? ['transport' => 'resend', 'key' => $values['api_key'] ?? null]
            : [
                'transport' => 'smtp',
                'scheme' => self::scheme((string) ($values['encryption'] ?? 'starttls')),
                'host' => $values['host'] ?? null,
                'port' => (int) ($values['port'] ?? 0),
                'username' => $values['username'] ?? null,
                'password' => $values['password'] ?? null,
            ];
    }

    /**
     * 暗号化の選び方を Symfony Mailer の scheme にする。
     */
    public static function scheme(string $encryption): string
    {
        return $encryption === 'ssl' ? 'smtps' : 'smtp';
    }

    /**
     * scheme(.env の MAIL_SCHEME など)を暗号化の選び方に戻す。
     */
    public static function encryption(?string $scheme): string
    {
        return $scheme === 'smtps' ? 'ssl' : 'starttls';
    }

    /**
     * 試しのメールを送る。送れたら null、送れなければ(パスワード・API キーを伏せた)理由を返す。
     *
     * @param  array{driver: string, host?: string|null, port?: int|string|null, encryption?: string|null, username?: string|null, password?: string|null, api_key?: string|null, from_address: string, from_name?: string|null}  $values
     */
    public static function sendTest(array $values, string $to, string $subject, string $body): ?string
    {
        config(['mail.mailers.'.self::TEST_MAILER => [...self::mailerConfig($values), 'timeout' => 15]]);

        try {
            Mail::mailer(self::TEST_MAILER)->raw(
                $body,
                fn ($message) => $message->to($to)->from($values['from_address'], filled($values['from_name'] ?? null) ? $values['from_name'] : 'Biscuit')->subject($subject),
            );

            return null;
        } catch (Throwable $exception) {
            return self::redact($exception->getMessage(), $values);
        } finally {
            // 試しの設定(API キー・パスワード)を、このあとに残さない
            Mail::purge(self::TEST_MAILER);
            config(['mail.mailers.'.self::TEST_MAILER => null]);
        }
    }

    /**
     * 文言から、パスワード・API キーを伏せる。
     *
     * @param  array<string, mixed>  $values
     */
    public static function redact(string $message, array $values): string
    {
        $secrets = array_filter([$values['password'] ?? null, $values['api_key'] ?? null], fn ($secret) => filled($secret));

        return $secrets === [] ? $message : str_replace($secrets, '[REDACTED]', $message);
    }
}
