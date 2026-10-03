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
 * インストーラーのメールの段。「試しに送って次へ」で SMTP の設定を確かめ、送れたら .env に書いて次の段へ進む。
 * 送れなければ、入力を残したまま理由を出す(パスワードは入れ直してもらう)。
 * 終えたあとに戻って直すときは、.env の今の設定を入れておき、パスワードを空のまま送ったら今のパスワードを使う(画面には出さない)。
 */
class MailController extends Controller
{
    public function show(InstallerManager $installer, EnvironmentWriter $environment): View
    {
        $configured = $installer->state()->isCompleted(InstallerStep::Mail);

        return view('installer.mail', [
            'installer' => $installer,
            'step' => InstallerStep::Mail,
            'configured' => $configured,
            'values' => [
                'host' => old('host', $configured ? $environment->get('MAIL_HOST') : ''),
                'port' => old('port', $configured ? $environment->get('MAIL_PORT') : 587),
                'encryption' => old('encryption', $configured && $environment->get('MAIL_SCHEME') === 'smtps' ? 'ssl' : 'starttls'),
                'username' => old('username', $configured ? $environment->get('MAIL_USERNAME') : ''),
                'from_address' => old('from_address', $configured ? $environment->get('MAIL_FROM_ADDRESS') : ''),
            ],
        ]);
    }

    public function store(MailRequest $request, MailInstaller $mail, InstallerManager $installer, EnvironmentWriter $environment): RedirectResponse
    {
        $values = $request->validated();

        if (($values['password'] ?? '') === '' && $installer->state()->isCompleted(InstallerStep::Mail)) {
            $values['password'] = $environment->get('MAIL_PASSWORD');
        }

        try {
            $mail->sendTest($values);
        } catch (InstallerStepException $exception) {
            return redirect()->route('installer.mail')->withInput($request->except('password'))->with('error', $exception->getMessage());
        }

        $mail->configure($values);

        return redirect()->route($installer->currentStep()->routeName());
    }
}
