<?php

namespace App\Http\Controllers;

use App\Models\Administrator;
use App\Models\SiteSetting;
use App\Support\AuditLogger;
use App\Support\TwoFactor\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * ログイン中の管理者自身の二段階認証の設定(画面上部の「二段階認証」。admin.two-factor.*)。
 * 既定はメールの確認コード。「認証アプリを設定する」で秘密鍵を作ってセッションに入れ、QR コードを読み取った認証アプリのコードを
 * 入れて確かめられたら、認証アプリ(Google Authenticator)に切り替える。今のパスワードを入れるとメールに戻せる。
 * スーパー管理者は、端末をなくした管理者の認証アプリを解除してメールに戻せる(reset()。ゲート reset-two-factor)。
 */
class TwoFactorController extends Controller
{
    /**
     * 設定中の(まだ確かめていない)秘密鍵を入れるセッションのキー。
     */
    private const PENDING_SECRET_KEY = 'admin_two_factor.pending_secret';

    public function show(Request $request, Totp $totp): View
    {
        /** @var Administrator $administrator */
        $administrator = $request->user('admin');
        $setup = ! $administrator->usesTotp() && $request->boolean('setup');
        $secret = null;
        $qrCode = null;

        if ($setup) {
            $secret = $request->session()->get(self::PENDING_SECRET_KEY) ?? $totp->generateSecret();
            $request->session()->put(self::PENDING_SECRET_KEY, $secret);
            $qrCode = $totp->qrCodeSvg($this->issuer(), $administrator->email, $secret);
        }

        return view('admin.two_factor.show', compact('administrator', 'setup', 'secret', 'qrCode'));
    }

    /**
     * 認証アプリのコードを確かめ、認証アプリを使うようにする。
     */
    public function update(Request $request, Totp $totp): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        /** @var Administrator $administrator */
        $administrator = $request->user('admin');
        $secret = $request->session()->get(self::PENDING_SECRET_KEY);
        $step = is_string($secret) ? $totp->verify($secret, (string) $request->input('code')) : null;

        if ($step === null) {
            throw ValidationException::withMessages(['code' => __('認証アプリのコードが違います。QR コードを読み取り直して、表示されているコードを入れてください。')]);
        }

        AuditLogger::updateWithLog($administrator, fn () => $administrator->enableTotp($secret, $step), ['two_factor' => 'totp_enabled']);
        $request->session()->forget(self::PENDING_SECRET_KEY);

        return redirect()->route('admin.two-factor.show')->with('status', __('認証アプリでの二段階認証を設定しました。次のログインから、認証アプリのコードを入れてください。'));
    }

    /**
     * 認証アプリをやめて、メールの確認コードに戻す(今のパスワードが必要)。
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password:admin']]);

        /** @var Administrator $administrator */
        $administrator = $request->user('admin');
        AuditLogger::updateWithLog($administrator, fn () => $administrator->disableTotp(), ['two_factor' => 'totp_disabled']);

        return redirect()->route('admin.two-factor.show')->with('status', __('認証アプリでの二段階認証をやめ、メールの確認コードに戻しました。'));
    }

    /**
     * ほかの管理者の認証アプリを解除して、メールの確認コードに戻す(スーパー管理者だけ。端末をなくしたときなど)。
     */
    public function reset(Administrator $administrator): RedirectResponse
    {
        AuditLogger::updateWithLog($administrator, fn () => $administrator->disableTotp(), ['two_factor' => 'totp_reset']);

        return redirect()->route('admin.show', $administrator)->with('status', __(':name の認証アプリを解除し、メールの確認コードに戻しました。', ['name' => $administrator->name]));
    }

    /**
     * 認証アプリに出す発行元(サイト名と管理画面)。
     */
    private function issuer(): string
    {
        return __(':site 管理画面', ['site' => SiteSetting::current()?->site_title ?? config('app.name')]);
    }
}
