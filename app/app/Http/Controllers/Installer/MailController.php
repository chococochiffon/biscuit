<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Installer\MailRequest;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use App\Installer\InstallerStepException;
use App\Installer\MailInstaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * インストーラーのメールの段。「試しに送って次へ」で SMTP の設定を確かめ、送れたら .env に書いて次の段へ進む。
 * 送れなければ、入力を残したまま理由を出す(パスワードは入れ直してもらう)。
 */
class MailController extends Controller
{
    public function show(InstallerManager $installer): View
    {
        return view('installer.mail', [
            'installer' => $installer,
            'step' => InstallerStep::Mail,
        ]);
    }

    public function store(MailRequest $request, MailInstaller $mail, InstallerManager $installer): RedirectResponse
    {
        $values = $request->validated();

        try {
            $mail->sendTest($values);
        } catch (InstallerStepException $exception) {
            return redirect()->route('installer.mail')->withInput($request->except('password'))->with('error', $exception->getMessage());
        }

        $mail->configure($values);

        return redirect()->route($installer->currentStep()->routeName());
    }
}
