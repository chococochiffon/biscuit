<?php

namespace App\Console\Commands;

use App\Enums\AdministratorRole;
use App\Models\Administrator;
use App\Notifications\UpdateAvailableNotification;
use App\Services\UpdateCheckService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * GitHub のリリースで新しい Biscuit のバージョンを確かめ(キャッシュを新しくし)、新しいバージョンがあれば
 * スーパー管理者にメールで知らせる(同じバージョンでは 1 回だけ)。毎日のスケジュール(routes/console.php)で動かす。
 */
#[Signature('biscuit:check-update')]
#[Description('新しい Biscuit のバージョンを確かめ、あればスーパー管理者にメールで知らせる')]
class CheckUpdateCommand extends Command
{
    public function handle(UpdateCheckService $updates): int
    {
        if (! config('biscuit.update_check.enabled')) {
            $this->info('更新の確認は止めています(BISCUIT_UPDATE_CHECK=false)。');

            return self::SUCCESS;
        }

        $updates->refresh();
        $release = $updates->availableUpdate(fetch: false);

        if ($release === null) {
            $this->info('今のバージョン('.config('biscuit.version').')が最新です。');

            return self::SUCCESS;
        }

        if (! $updates->shouldNotify($release['version'])) {
            $this->info("新しいバージョン {$release['version']} はお知らせ済みです。");

            return self::SUCCESS;
        }

        $administrators = Administrator::query()->where('role', AdministratorRole::SuperAdmin)->get();
        Notification::send($administrators, new UpdateAvailableNotification($release));
        $updates->markNotified($release['version']);
        $this->info("新しいバージョン {$release['version']} をスーパー管理者 {$administrators->count()} 人に知らせました。");

        return self::SUCCESS;
    }
}
