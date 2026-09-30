<?php

namespace App\Notifications;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 管理者からユーザーへの招待のメール。リンクは公開側(chococo)の招待ページ(/invitation)を開き、
 * プロフィールとパスワードを登録してもらう。キューのワーカーを動かしていないため、その場で送る(ShouldQueue にしない)。
 */
class UserInvitationNotification extends Notification
{
    /**
     * @param  string  $token  招待のトークン(UserInvitation::issue() が発行した平文)
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
            ->subject(__('【:site】ご招待のお知らせ', ['site' => $siteTitle]))
            ->markdown('mail.invitation', [
                'siteTitle' => $siteTitle,
                'siteUrl' => SiteSetting::frontUrl(),
                'invitationUrl' => $this->invitationUrl($notifiable),
                'expireHours' => round(config('auth.invitations.expire') / 60, 1),
            ]);
    }

    /**
     * 公開側(chococo)の招待ページの URL(例: https://example.com/invitation?token=...&email=...)。
     */
    public function invitationUrl(User $notifiable): string
    {
        return SiteSetting::frontUrl().'/invitation?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);
    }
}
