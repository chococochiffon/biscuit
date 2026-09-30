<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdministratorLoginRequest;
use App\Models\Administrator;
use App\Models\LoginCode;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 管理者のログイン・ログアウト。ログインは二段階で、メールアドレスとパスワードが正しければ確認コードをメールで送り、
 * コードを入力するとログインが完了する。どのログインへのコードかは、セッションに入れたチャレンジで見分ける。
 * ログイン・ログアウト・ログイン失敗は、認証イベントのリスナー(RecordAdministratorAuthentication)が監査ログに残す。
 */
class AdministratorSessionController extends Controller
{
    /**
     * 確認コードのチャレンジ・送り先のメールアドレスを入れるセッションのキー。
     */
    private const CHALLENGE_KEY = 'admin_login.challenge';

    private const EMAIL_KEY = 'admin_login.email';

    /**
     * ログイン画面を表示する。
     */
    public function create(): View
    {
        return view('admin.auth.login');
    }

    /**
     * メールアドレスとパスワードを確かめ、確認コードをメールで送って、コードの入力画面へ進む。
     */
    public function store(AdministratorLoginRequest $request): RedirectResponse
    {
        $administrator = $request->validateCredentials();

        $challenge = DB::transaction(function () use ($administrator) {
            $challenge = LoginCode::issue($administrator);
            AuditLogger::record(AuditAction::LoginCodeSent, $administrator, actor: $administrator);

            return $challenge;
        });

        $request->session()->put([self::CHALLENGE_KEY => $challenge, self::EMAIL_KEY => $administrator->email]);

        return redirect()->route('admin.login.verify');
    }

    /**
     * 確認コードの入力画面を表示する(パスワードを確かめる前なら、ログイン画面へ戻す)。
     */
    public function verifyForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::CHALLENGE_KEY)) {
            return redirect()->route('admin.login');
        }

        return view('admin.auth.verify', ['email' => $request->session()->get(self::EMAIL_KEY)]);
    }

    /**
     * 確認コードを確かめ、正しければログインする。
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $challenge = (string) $request->session()->get(self::CHALLENGE_KEY);
        $administrator = $challenge === '' ? null : LoginCode::verify(Administrator::class, $challenge, (string) $request->input('code'));

        if ($administrator === null) {
            AuditLogger::record(AuditAction::LoginFailed, 'administrator', metadata: ['email' => (string) $request->session()->get(self::EMAIL_KEY), 'reason' => 'login_code']);

            throw ValidationException::withMessages(['code' => __('確認コードが違うか、有効期限が切れています。')]);
        }

        $request->session()->forget([self::CHALLENGE_KEY, self::EMAIL_KEY]);
        Auth::guard('admin')->login($administrator);
        $request->session()->regenerate();

        $administrator->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('admin.dashboard');
    }

    /**
     * 確認コードを送り直す(それまでのコードは使えなくなる)。
     */
    public function resend(Request $request): RedirectResponse
    {
        $challenge = LoginCode::resend(Administrator::class, (string) $request->session()->get(self::CHALLENGE_KEY));

        if ($challenge === null) {
            $request->session()->forget([self::CHALLENGE_KEY, self::EMAIL_KEY]);

            return redirect()->route('admin.login');
        }

        $request->session()->put(self::CHALLENGE_KEY, $challenge);
        AuditLogger::record(AuditAction::LoginCodeSent, 'administrator', metadata: ['email' => (string) $request->session()->get(self::EMAIL_KEY), 'resent' => true]);

        return redirect()->route('admin.login.verify')->with('status', __('確認コードを送り直しました。'));
    }

    /**
     * ログアウト処理を行う。
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
