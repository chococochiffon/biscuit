<?php

namespace App\Notifications;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * chococo のマイページのパスワード再設定のメール。リンクは公開側(chococo)の再設定ページ(/reset-password)を開く。
 * キューのワーカーを動かしていないため、その場で送る(ShouldQueue にしない)。
 */
class ResetPasswordNotification extends Notification
{
    /**
     * @param  string  $token  再設定用のトークン(パスワードブローカーが発行したもの)
     */
    public function __construct(public string $token) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $siteTitle = SiteSetting::current()?->site_title ?: config('app.name');

        return (new MailMessage)
            ->from(config('mail.from.address'), $siteTitle)
            ->subject(__('【:site】パスワード再設定のご案内', ['site' => $siteTitle]))
            ->markdown('mail.reset-password', [
                'siteTitle' => $siteTitle,
                'siteUrl' => SiteSetting::frontUrl(),
                'resetUrl' => $this->resetUrl($notifiable),
                'expireMinutes' => config('auth.passwords.users.expire'),
            ]);
    }

    /**
     * 公開側(chococo)の再設定ページの URL(例: https://example.com/reset-password?token=...&email=...)。
     */
    public function resetUrl(User $notifiable): string
    {
        return SiteSetting::frontUrl().'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
