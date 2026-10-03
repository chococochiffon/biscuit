<?php

namespace App\Installer;

use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * メールの段: SMTP の設定で試しにメールを送り、送れたら .env の MAIL_* に書く。
 * 管理画面のログインはメールの確認コードの二段階認証のため、メールを送れないと作った管理者がログインできない。
 * 失敗したときの文言から、SMTP のパスワードは伏せる。
 */
class MailInstaller
{
    public function __construct(private EnvironmentWriter $environment, private InstallationState $state) {}

    /**
     * 試しにメールを送る。送れなければ InstallerStepException。
     *
     * @param  array{host: string, port: int|string, encryption: string, username: string|null, password: string|null, from_address: string, test_to: string}  $values
     */
    public function sendTest(array $values): void
    {
        config(['mail.mailers.installer_test' => [
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
            $error = $values['password'] ? str_replace($values['password'], '[REDACTED]', $exception->getMessage()) : $exception->getMessage();
            InstallerLog::error('試しのメールを送れませんでした。', ['host' => $values['host'], 'port' => $values['port'], 'error' => $error]);

            throw new InstallerStepException('mail', __('試しのメールを送れませんでした。SMTP の設定を確かめてください。(:error)', ['error' => mb_strimwidth($error, 0, 300, '…')]));
        }

        InstallerLog::info('試しのメールを送りました。', ['host' => $values['host'], 'port' => $values['port'], 'password' => $values['password']]);
    }

    /**
     * 試しのメールを送れた設定を .env に書く。
     *
     * @param  array{host: string, port: int|string, encryption: string, username: string|null, password: string|null, from_address: string}  $values
     */
    public function configure(array $values): void
    {
        $this->environment->set([
            'MAIL_MAILER' => 'smtp',
            'MAIL_SCHEME' => $this->scheme($values['encryption']),
            'MAIL_HOST' => $values['host'],
            'MAIL_PORT' => (string) $values['port'],
            'MAIL_USERNAME' => (string) $values['username'],
            'MAIL_PASSWORD' => (string) $values['password'],
            'MAIL_FROM_ADDRESS' => $values['from_address'],
        ]);

        $this->state->markCompleted(InstallerStep::Mail);
        InstallerLog::info('メールの設定を .env に書きました。', ['host' => $values['host'], 'port' => $values['port']]);
    }

    /**
     * 暗号化の選び方を Symfony Mailer の scheme にする(starttls は smtp のまま、つながったあとに使えれば STARTTLS にする)。
     */
    private function scheme(string $encryption): string
    {
        return $encryption === 'ssl' ? 'smtps' : 'smtp';
    }
}
