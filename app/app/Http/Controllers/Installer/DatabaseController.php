<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Installer\DatabaseRequest;
use App\Installer\DatabaseInstaller;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use App\Installer\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * インストーラーのデータベースの段。DB 名・ユーザー・パスワードを受け取り、.env の DB_* に書く(DatabaseInstaller)。
 * 「安全なパスワードを生成」は、サーバーで生成したパスワードを入力欄に入れた画面をその場で返す
 * (セッションなどには残さない。控えてから進めてもらう)。
 */
class DatabaseController extends Controller
{
    public function show(InstallerManager $installer): View
    {
        return $this->form($installer, old('database', $installer->state()->get('db_database', 'biscuit')), old('username', $installer->state()->get('db_username', 'biscuit')));
    }

    /**
     * 安全なパスワードを生成して、入力欄に入れた画面を返す(入力中の DB 名・ユーザーは残す)。
     */
    public function generate(Request $request, InstallerManager $installer): View
    {
        return $this->form($installer, (string) $request->input('database'), (string) $request->input('username'), PasswordPolicy::generate());
    }

    public function store(DatabaseRequest $request, DatabaseInstaller $database, InstallerManager $installer): RedirectResponse
    {
        $database->configure($request->validated('database'), $request->validated('username'), $request->validated('password'));

        return redirect()->route($installer->currentStep()->routeName());
    }

    private function form(InstallerManager $installer, string $database, string $username, ?string $generatedPassword = null): View
    {
        return view('installer.database', [
            'installer' => $installer,
            'step' => InstallerStep::Database,
            'database' => $database,
            'username' => $username,
            'generatedPassword' => $generatedPassword,
        ]);
    }
}
