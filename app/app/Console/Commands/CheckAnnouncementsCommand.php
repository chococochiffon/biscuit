<?php

namespace App\Console\Commands;

use App\Enums\AdministratorRole;
use App\Models\Administrator;
use App\Notifications\AnnouncementNotification;
use App\Services\AnnouncementService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Biscuit からのお知らせを読み直し(キャッシュを新しくし)、まだ知らせていない重要・セキュリティのお知らせを
 * スーパー管理者にメールで知らせる(お知らせごとに 1 回だけ)。毎日のスケジュール(routes/console.php)で動かす。
 */
#[Signature('biscuit:check-announcements')]
#[Description('Biscuit からのお知らせを読み、重要・セキュリティのお知らせをスーパー管理者にメールで知らせる')]
class CheckAnnouncementsCommand extends Command
{
    public function handle(AnnouncementService $announcements): int
    {
        if (! config('biscuit.announcements.enabled')) {
            $this->info('お知らせの読み込みは止めています(BISCUIT_ANNOUNCEMENTS=false)。');

            return self::SUCCESS;
        }

        $announcements->refresh();
        $pending = array_values(array_filter($announcements->urgent(fetch: false), fn (array $announcement) => $announcements->shouldNotify($announcement['id'])));

        if ($pending === []) {
            $this->info('新しく知らせる重要なお知らせはありません。');

            return self::SUCCESS;
        }

        $administrators = Administrator::query()->where('role', AdministratorRole::SuperAdmin)->get();

        foreach ($pending as $announcement) {
            Notification::send($administrators, new AnnouncementNotification($announcement));
            $announcements->markNotified($announcement['id']);
            $this->info("お知らせ「{$announcement['title']}」をスーパー管理者 {$administrators->count()} 人に知らせました。");
        }

        return self::SUCCESS;
    }
}
