<?php

namespace App\Installer;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * 完了の段: 完了したら(finish())ロックファイルを作って途中の状態とホストとの合図を消し、
 * 設定・ルート・ビューのキャッシュを作る(php artisan optimize。作れなくてもインストールは完了とする)。
 * install.sh はロックファイルを見て、URL を変えた公開側のコンテナを起動し直して終わる。
 */
class InstallerFinalizer
{
    public function __construct(private InstallerManager $installer) {}

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
}
