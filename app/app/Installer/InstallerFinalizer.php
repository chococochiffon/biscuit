<?php

namespace App\Installer;

use App\Models\Administrator;
use App\Models\PageBuilder;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * 完了の段: インストールの内容を確かめ(checks())、完了したら(finish())ロックファイルを作って途中の状態とホストとの合図を消し、
 * 設定・ルート・ビューのキャッシュを作る(php artisan optimize。作れなくてもインストールは完了とする)。
 * install.sh はロックファイルを見て、URL を変えた公開側のコンテナを起動し直して終わる。
 */
class InstallerFinalizer
{
    public function __construct(private InstallerManager $installer) {}

    /**
     * インストールの内容の確認(すべて ok なら完了できる)。
     *
     * @return list<array{label: string, ok: bool, detail: string|null}>
     */
    public function checks(): array
    {
        $databaseOk = $this->safely(fn () => DB::connection()->getPdo() !== null);
        $setting = $databaseOk ? $this->safely(fn () => SiteSetting::current()) : null;
        $administrator = $databaseOk ? $this->safely(fn () => Administrator::query()->orderBy('id')->first()) : null;

        return [
            ['label' => __('データベースに接続できる'), 'ok' => $databaseOk, 'detail' => null],
            ['label' => __('テーブルを用意した(マイグレーション)'), 'ok' => $databaseOk && $this->safely(fn () => Schema::hasTable('administrators')), 'detail' => null],
            ['label' => __('サイト'), 'ok' => $setting instanceof SiteSetting, 'detail' => $setting?->site_title],
            ['label' => __('管理者'), 'ok' => $administrator instanceof Administrator, 'detail' => $administrator?->email],
            ['label' => __('デザイン'), 'ok' => $databaseOk && $this->safely(fn () => PageBuilder::top()?->isPublished() === true), 'detail' => __('デフォルトのデザイン')],
            ['label' => __('storage に書き込める'), 'ok' => is_writable(storage_path()), 'detail' => null],
        ];
    }

    public function finish(): void
    {
        $this->installer->lock();
        Storage::disk('local')->deleteDirectory(InstallationState::DIRECTORY);
        InstallerLog::info('インストールを完了しました。', ['version' => config('biscuit.version')]);

        // テストでは設定・ルートのキャッシュを作らない(作ると本物の bootstrap/cache に残る)
        if (app()->runningUnitTests()) {
            return;
        }

        try {
            Artisan::call('optimize');
        } catch (Throwable $exception) {
            InstallerLog::error('キャッシュを作れませんでした(インストールは完了しています)。', ['error' => $exception->getMessage()]);
        }
    }

    private function safely(callable $check): mixed
    {
        try {
            return $check();
        } catch (Throwable) {
            return false;
        }
    }
}
