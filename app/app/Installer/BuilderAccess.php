<?php

namespace App\Installer;

use App\Models\Administrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * インストーラーの中のビルダーを使えるか。管理者の段で最初の管理者を作ったブラウザのセッションだけが使える
 * (インストール中に、ほかの人が /install を開いてもビルダーで書き込めないようにする)。
 * セッションが切れたとき(ブラウザを閉じたなど)は、作った管理者のメールアドレスとパスワードを入れ直せば使える(unlock())。
 * インストールを終えたら、インストーラーのルートごと入れなくなる(EnsureNotInstalled)。
 */
class BuilderAccess
{
    public const SESSION_KEY = 'installer.builder_administrator_id';

    public function __construct(private InstallationState $state) {}

    public function grant(Request $request, Administrator $administrator): void
    {
        $request->session()->put(self::SESSION_KEY, $administrator->id);
    }

    /**
     * このセッションがビルダーを使えるなら、その管理者(インストーラーが作った最初の管理者)。使えなければ null。
     */
    public function administrator(Request $request): ?Administrator
    {
        $id = $request->session()->get(self::SESSION_KEY);

        if ($id === null || ! $this->state->isCompleted(InstallerStep::Administrator)) {
            return null;
        }

        $administrator = Administrator::query()->orderBy('id')->first();

        return $administrator !== null && $administrator->id === $id ? $administrator : null;
    }

    /**
     * 作った管理者のメールアドレスとパスワードが合えば、このセッションでビルダーを使えるようにする。
     */
    public function unlock(Request $request, string $email, string $password): bool
    {
        $administrator = $this->state->isCompleted(InstallerStep::Administrator) ? Administrator::query()->orderBy('id')->first() : null;

        if ($administrator === null || strcasecmp($administrator->email, $email) !== 0 || ! Hash::check($password, $administrator->password)) {
            InstallerLog::error('インストーラーのビルダーのロックを解除できませんでした。', ['email' => $email]);

            return false;
        }

        $request->session()->regenerate();
        $this->grant($request, $administrator);

        return true;
    }
}
