<?php

use App\Installer\HealthChecker;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 新しい Biscuit のバージョンを毎日確かめ、あればスーパー管理者にメールで知らせる(本番では php artisan schedule:run を cron で動かす)
Schedule::command('biscuit:check-update')->dailyAt('09:00');

// Biscuit からのお知らせを毎日読み、重要・セキュリティのお知らせをスーパー管理者にメールで知らせる
Schedule::command('biscuit:check-announcements')->dailyAt('09:05');

// データベース・画像・.env のバックアップを毎日作る(新しい 7 個を残す。BISCUIT_BACKUP_DAILY=false で止める)
Schedule::command('biscuit:backup --reason=daily')
    ->dailyAt(config('biscuit.backup.daily_at'))
    ->when(fn () => config('biscuit.backup.daily'));

// スケジューラーが動いている合図を毎分置く(インストールの確認と、これからの System Doctor が見る)
Schedule::call(fn () => HealthChecker::beat())->everyMinute()->name('biscuit:scheduler-heartbeat');
