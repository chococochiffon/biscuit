<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Installer\MailRequest;
use App\Installer\EnvironmentWriter;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use App\Installer\InstallerStepException;
use App\Installer\MailInstaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * インストーラーのメールの段。送り方(SMTP か Resend)を選び、「試しに送って次へ」で設定を確かめ、送れたら .env に書いて次の段へ進む。
 * 送れなければ、入力を残したまま理由を出す(パスワード・API キーは入れ直してもらう)。
 * 終えたあとに戻って直すときは、.env の今の設定を入れておき、同じ送り方でパスワード・API キーを空のまま送ったら
 * 今の値を使う(画面には出さない)。
 */
class MailController extends Controller
{
    /**
     * 秘密の値の入力欄と、.env のキー(送り方ごと)。
     */
    private const SECRETS = [
        'smtp' => ['password', 'MAIL_PASSWORD'],
        'resend' => ['api_key', 'RESEND_API_KEY'],
    ];

    public function show(InstallerManager $installer, EnvironmentWriter $environment): View
    {
        $configured = $installer->state()->isCompleted(InstallerStep::Mail);
        $current = $configured ? $this->currentDriver($environment) : null;
        $smtp = $current === 'smtp';

        return view('installer.mail', [
            'installer' => $installer,
            'step' => InstallerStep::Mail,
            'current' => $current,
            'values' => [
                'driver' => old('driver', $current ?? 'smtp'),
                'host' => old('host', $smtp ? $environment->get('MAIL_HOST') : ''),
                'port' => old('port', $smtp ? $environment->get('MAIL_PORT') : 587),
                'encryption' => old('encryption', $smtp && $environment->get('MAIL_SCHEME') === 'smtps' ? 'ssl' : 'starttls'),
                'username' => old('username', $smtp ? $environment->get('MAIL_USERNAME') : ''),
                'from_address' => old('from_address', $configured ? $environment->get('MAIL_FROM_ADDRESS') : ''),
            ],
        ]);
    }

    public function store(MailRequest $request, MailInstaller $mail, InstallerManager $installer, EnvironmentWriter $environment): RedirectResponse
    {
        $values = $request->validated();
        [$field, $key] = self::SECRETS[$values['driver']];

        if (($values[$field] ?? '') === '' && $installer->state()->isCompleted(InstallerStep::Mail) && $this->currentDriver($environment) === $values['driver']) {
            $values[$field] = $environment->get($key);
        }

        if ($values['driver'] === 'resend' && blank($values['api_key'] ?? null)) {
            return redirect()->route('installer.mail')
                ->withInput($request->except('password', 'api_key'))
                ->withErrors(['api_key' => __('validation.required', ['attribute' => __('Resend の API キー')])]);
        }

        try {
            $mail->sendTest($values);
        } catch (InstallerStepException $exception) {
            return redirect()->route('installer.mail')->withInput($request->except('password', 'api_key'))->with('error', $exception->getMessage());
        }

        $mail->configure($values);

        return redirect()->route($installer->currentStep()->routeName());
    }

    /**
     * .env の今の送り方(Resend でなければ SMTP とみなす)。
     */
    private function currentDriver(EnvironmentWriter $environment): string
    {
        return $environment->get('MAIL_MAILER') === 'resend' ? 'resend' : 'smtp';
    }
}
