<?php

namespace App\Notifications;

use App\Models\Administrator;
use App\Models\SiteSetting;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 新しい Biscuit のバージョンが公開されたことを、スーパー管理者に知らせるメール(biscuit:check-update が送る。同じバージョンでは 1 回だけ)。
 * キューのワーカーを動かしていないため、その場で送る(ShouldQueue にしない)。
 */
class UpdateAvailableNotification extends Notification
{
    /**
     * @param  array{version: string, name: string, url: string, published_at: CarbonImmutable|null, notes: string}  $release
     */
    public function __construct(public array $release) {}

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
            ->subject(__('【:site】Biscuit の新しいバージョン :version が公開されています', ['site' => $siteTitle, 'version' => $this->release['version']]))
            ->markdown('mail.update-available', [
                'siteTitle' => $siteTitle,
                'siteUrl' => route('admin.dashboard'),
                'release' => $this->release,
                'currentVersion' => config('biscuit.version'),
            ]);
    }
}
