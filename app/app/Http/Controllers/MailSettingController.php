<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMailSettingRequest;
use App\Models\MailSetting;
use App\Models\SiteSetting;
use App\Support\AuditLogger;
use App\Support\MailTransport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * システムが使うメールの設定(サイドメニューの「システム › メールの設定」。スーパー管理者だけが使える。ゲート manage-mail-settings)。
 * 設定は DB(MailSetting)に保存し、.env の MAIL_* より優先する。保存の前に試しのメールを送り、送れたときだけ保存する
 * (設定を間違えると、確認コードが届かず管理画面にログインできなくなるため)。
 * パスワード・API キーは画面に出さず、同じ送り方で空のまま送ったら今の値(未保存なら .env の値)を使う。
 * 「.env の設定に戻す」で画面の設定を削除(論理削除)すると、.env の設定に戻る。
 */
class MailSettingController extends Controller
{
    public function edit(): View
    {
        $setting = MailSetting::current();
        $current = $this->currentValues($setting);

        return view('admin.mail_settings.edit', [
            'setting' => $setting,
            'current' => $current,
            'values' => [
                'driver' => old('driver', $current['driver']),
                'host' => old('host', $current['host']),
                'port' => old('port', $current['port'] ?? 587),
                'encryption' => old('encryption', $current['encryption']),
                'username' => old('username', $current['username']),
                'from_address' => old('from_address', $current['from_address']),
                'from_name' => old('from_name', $setting?->from_name),
            ],
        ]);
    }

    public function update(UpdateMailSettingRequest $request): RedirectResponse
    {
        $values = $request->validated();
        $setting = MailSetting::current();
        $current = $this->currentValues($setting);
        $secretField = $values['driver'] === 'resend' ? 'api_key' : 'password';
        $secretChanged = filled($values[$secretField] ?? null);

        // パスワード・API キーが空なら、同じ送り方の今の値を使う
        if (! $secretChanged && $current['driver'] === $values['driver']) {
            $values[$secretField] = $current[$secretField];
        }

        if ($values['driver'] === 'resend' && blank($values['api_key'] ?? null)) {
            return back()->withInput($request->except('password', 'api_key'))
                ->withErrors(['api_key' => __('validation.required', ['attribute' => __('Resend の API キー')])]);
        }

        $error = MailTransport::sendTest(
            $values,
            $values['test_to'],
            __('【:site】メールの設定の確認', ['site' => SiteSetting::current()?->site_title ?? config('app.name')]),
            __('Biscuit の管理画面の「メールの設定」から送った、試しのメールです。このメールが届いていれば、メールの設定は正しく動いています。'),
        );

        if ($error !== null) {
            return back()->withInput($request->except('password', 'api_key'))
                ->with('error', __('試しのメールを送れなかったため、保存していません。設定を確かめてください。(:error)', ['error' => mb_strimwidth($error, 0, 300, '…')]));
        }

        $attributes = [
            'driver' => $values['driver'],
            'host' => $values['host'] ?? null,
            'port' => $values['port'] ?? null,
            'encryption' => $values['encryption'] ?? null,
            'username' => $values['username'] ?? null,
            'password' => $values['password'] ?? null,
            'api_key' => $values['api_key'] ?? null,
            'from_address' => $values['from_address'],
            'from_name' => $values['from_name'] ?? null,
        ];
        // パスワード・API キーの値は監査ログに残さず、変えたかだけを残す
        $metadata = ['test_to' => $values['test_to'], $secretField.'_changed' => $secretChanged];

        DB::transaction(function () use ($setting, $attributes, $metadata) {
            if ($setting === null) {
                AuditLogger::created(MailSetting::create($attributes), $metadata);

                return;
            }

            $before = AuditLogger::snapshot($setting);
            $setting->update($attributes);
            AuditLogger::updated($setting, $before, $metadata);
        });

        // このリクエストの中で作ったメーラーを捨て、新しい設定で作り直させる
        Mail::purge();

        return redirect()->route('admin.mail-settings.edit')->with('status', __('試しのメールを送り、メールの設定を保存しました。'));
    }

    /**
     * 画面の設定をやめて、.env の設定に戻す。
     */
    public function destroy(): RedirectResponse
    {
        $setting = MailSetting::current();

        if ($setting !== null) {
            AuditLogger::deleteWithLog($setting);
        }

        return redirect()->route('admin.mail-settings.edit')->with('status', __('画面の設定をやめ、.env の設定に戻しました。'));
    }

    /**
     * 今使っている設定(画面で保存した設定、なければ .env の設定)。
     *
     * @return array{source: string, driver: string, host: string|null, port: int|null, encryption: string, username: string|null, password: string|null, api_key: string|null, from_address: string|null}
     */
    private function currentValues(?MailSetting $setting): array
    {
        if ($setting !== null) {
            return [
                'source' => 'database',
                ...$setting->transportValues(),
                'encryption' => $setting->encryption ?? 'starttls',
                'from_address' => $setting->from_address,
            ];
        }

        $smtp = (array) config('mail.mailers.smtp');

        return [
            'source' => 'env',
            'driver' => config('mail.default') === 'resend' ? 'resend' : 'smtp',
            'host' => $smtp['host'] ?? null,
            'port' => isset($smtp['port']) ? (int) $smtp['port'] : null,
            'encryption' => MailTransport::encryption($smtp['scheme'] ?? null),
            'username' => $smtp['username'] ?? null,
            'password' => $smtp['password'] ?? null,
            'api_key' => config('services.resend.key'),
            'from_address' => config('mail.from.address'),
        ];
    }
}
