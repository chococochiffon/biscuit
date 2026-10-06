<?php

namespace App\Installer;

use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * メールの段: SMTP か Resend(API)の設定で試しにメールを送り、送れたら .env の MAIL_*(Resend は RESEND_API_KEY も)に書く。
 * 管理画面のログインはメールの確認コードの二段階認証のため、メールを送れないと作った管理者がログインできない。
 * 失敗したときの文言から、SMTP のパスワード・Resend の API キーは伏せる。
 */
class MailInstaller
{
    /**
     * 選べる送り方(MAIL_MAILER の値)。
     *
     * @var list<string>
     */
    public const DRIVERS = ['smtp', 'resend'];

    public function __construct(private EnvironmentWriter $environment, private InstallationState $state) {}

    /**
     * 試しにメールを送る。送れなければ InstallerStepException。
     *
     * @param  array{driver: string, host?: string, port?: int|string, encryption?: string, username?: string|null, password?: string|null, api_key?: string|null, from_address: string, test_to: string}  $values
     */
    public function sendTest(array $values): void
    {
        config(['mail.mailers.installer_test' => $values['driver'] === 'resend'
            ? ['transport' => 'resend', 'key' => $values['api_key']]
            : [
                'transport' => 'smtp',
                'scheme' => $this->scheme($values['encryption']),
                'host' => $values['host'],
                'port' => (int) $values['port'],
                'username' => $values['username'],
                'password' => $values['password'],
                'timeout' => 15,
            ]]);

        try {
            Mail::mailer('installer_test')->raw(
                __('Biscuit のインストーラーから送った、試しのメールです。このメールが届いていれば、メールの設定は正しく動いています。'),
                fn ($message) => $message->to($values['test_to'])->from($values['from_address'], 'Biscuit')->subject(__('【Biscuit】メールの設定の確認')),
            );
        } catch (Throwable $exception) {
            $error = $this->redact($exception->getMessage(), $values);
            InstallerLog::error('試しのメールを送れませんでした。', [...$this->logContext($values), 'error' => $error]);

            throw new InstallerStepException('mail', $values['driver'] === 'resend'
                ? __('試しのメールを送れませんでした。Resend の API キーと、送信元のドメインを Resend で認証したかを確かめてください。(:error)', ['error' => mb_strimwidth($error, 0, 300, '…')])
                : __('試しのメールを送れませんでした。SMTP の設定を確かめてください。(:error)', ['error' => mb_strimwidth($error, 0, 300, '…')]));
        } finally {
            // 試しの設定(API キー・パスワード)を、このリクエストのあとに残さない
            Mail::purge('installer_test');
            config(['mail.mailers.installer_test' => null]);
        }

        InstallerLog::info('試しのメールを送りました。', $this->logContext($values));
    }

    /**
     * 試しのメールを送れた設定を .env に書く。
     *
     * @param  array{driver: string, host?: string, port?: int|string, encryption?: string, username?: string|null, password?: string|null, api_key?: string|null, from_address: string}  $values
     */
    public function configure(array $values): void
    {
        $this->environment->set($values['driver'] === 'resend'
            ? [
                'MAIL_MAILER' => 'resend',
                'RESEND_API_KEY' => (string) $values['api_key'],
                'MAIL_FROM_ADDRESS' => $values['from_address'],
            ]
            : [
                'MAIL_MAILER' => 'smtp',
                'MAIL_SCHEME' => $this->scheme($values['encryption']),
                'MAIL_HOST' => $values['host'],
                'MAIL_PORT' => (string) $values['port'],
                'MAIL_USERNAME' => (string) $values['username'],
                'MAIL_PASSWORD' => (string) $values['password'],
                'MAIL_FROM_ADDRESS' => $values['from_address'],
            ]);

        $this->state->markCompleted(InstallerStep::Mail);
        InstallerLog::info('メールの設定を .env に書きました。', $this->logContext($values));
    }

    /**
     * 暗号化の選び方を Symfony Mailer の scheme にする(starttls は smtp のまま、つながったあとに使えれば STARTTLS にする)。
     */
    private function scheme(string $encryption): string
    {
        return $encryption === 'ssl' ? 'smtps' : 'smtp';
    }

    /**
     * 失敗の文言から、パスワード・API キーを伏せる。
     *
     * @param  array<string, mixed>  $values
     */
    private function redact(string $message, array $values): string
    {
        $secrets = array_filter([$values['password'] ?? null, $values['api_key'] ?? null], fn ($secret) => filled($secret));

        return $secrets === [] ? $message : str_replace($secrets, '[REDACTED]', $message);
    }

    /**
     * ログに残す送り先(秘密の値は入れない)。
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function logContext(array $values): array
    {
        return $values['driver'] === 'resend'
            ? ['driver' => 'resend']
            : ['driver' => 'smtp', 'host' => $values['host'], 'port' => $values['port']];
    }
}
