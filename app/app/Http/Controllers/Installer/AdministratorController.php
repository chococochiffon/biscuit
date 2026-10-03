<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Installer\AdministratorRequest;
use App\Installer\AdministratorInstaller;
use App\Installer\BuilderAccess;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use App\Installer\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * インストーラーの管理者の段(最初のスーパー管理者を作る)。作り終えたあとは入れない(installer.step。2 人目を作らない)。
 * 「安全なパスワードを生成」は、データベースの段と同じく、生成したパスワードを入力欄に入れた画面をその場で返す(セッションには残さない)。
 */
class AdministratorController extends Controller
{
    public function show(InstallerManager $installer): View
    {
        return $this->form($installer, (string) old('name'), (string) old('email'));
    }

    /**
     * 安全なパスワードを生成して、入力欄に入れた画面を返す(入力中の表示名・メールアドレスは残す)。
     */
    public function generate(Request $request, InstallerManager $installer): View
    {
        return $this->form($installer, (string) $request->input('name'), (string) $request->input('email'), PasswordPolicy::generate());
    }

    public function store(AdministratorRequest $request, AdministratorInstaller $administrators, InstallerManager $installer, BuilderAccess $access): RedirectResponse
    {
        $administrator = $administrators->create($request->validated('name'), $request->validated('email'), $request->validated('password'));
        // このブラウザでは、デザインの段のビルダーを使える
        $access->grant($request, $administrator);

        return redirect()->route($installer->currentStep()->routeName());
    }

    private function form(InstallerManager $installer, string $name, string $email, ?string $generatedPassword = null): View
    {
        return view('installer.administrator', [
            'installer' => $installer,
            'step' => InstallerStep::Administrator,
            'name' => $name,
            'email' => $email,
            'generatedPassword' => $generatedPassword,
        ]);
    }
}
