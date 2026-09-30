<?php

namespace App\Notifications;

use App\Models\Administrator;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 二段階認証の確認コードのメール(管理画面と chococo のマイページのログイン)。
 * キューのワーカーを動かしていないため、その場で送る(ShouldQueue にしない)。
 */
class LoginCodeNotification extends Notification
{
    /**
     * @param  string  $code  6 桁の確認コード
     */
    public function __construct(public string $code) {}

    /**
     * @return list<string>
     */
    public function via(Administrator|User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Administrator|User $notifiable): MailMessage
    {
        $siteTitle = SiteSetting::current()?->site_title ?: config('app.name');

        return (new MailMessage)
            ->from(config('mail.from.address'), $siteTitle)
            ->subject(__('【:site】ログインの確認コード', ['site' => $siteTitle]))
            ->markdown('mail.login-code', [
                'siteTitle' => $siteTitle,
                'siteUrl' => $notifiable instanceof Administrator ? route('admin.login') : SiteSetting::frontUrl(),
                'code' => $this->code,
                'expireMinutes' => config('auth.login_codes.expire'),
            ]);
    }
}
