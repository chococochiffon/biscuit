<?php

namespace App\Notifications;

use App\Models\Administrator;
use App\Models\SiteSetting;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Biscuit からの重要・セキュリティのお知らせを、スーパー管理者に知らせるメール(biscuit:check-announcements が送る。お知らせごとに 1 回だけ)。
 * キューのワーカーを動かしていないため、その場で送る(ShouldQueue にしない)。
 */
class AnnouncementNotification extends Notification
{
    /**
     * @param  array{id: string, date: CarbonImmutable, level: string, title: string, body: string, url: string|null}  $announcement
     */
    public function __construct(public array $announcement) {}

    /**
     * @return list<string>
     */
    public function via(Administrator $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Administrator $notifiable): MailMessage
    {
        $siteTitle = SiteSetting::current()?->site_title ?: config('app.name');

        return (new MailMessage)
            ->from(config('mail.from.address'), $siteTitle)
            ->subject(__('【:site】Biscuit からのお知らせ: :title', ['site' => $siteTitle, 'title' => $this->announcement['title']]))
            ->markdown('mail.announcement', [
                'siteTitle' => $siteTitle,
                'siteUrl' => route('admin.dashboard'),
                'announcement' => $this->announcement,
            ]);
    }
}
