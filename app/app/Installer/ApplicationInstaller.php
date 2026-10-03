<?php

namespace App\Installer;

use Database\Seeders\InstallSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * アプリケーションの段の処理(biscuit:install --step=application。install.sh がサービスを起動したあとに動かす)。
 * DB の接続確認 → マイグレーション → 本番用の初期データ(InstallSeeder) → storage のリンク → キャッシュの削除。終えたらアプリケーションの段を済みにする。
 * 失敗したら、何に失敗したかを InstallerStepException で返す(何度やり直してもよい)。
 */
class ApplicationInstaller
{
    /**
     * DB の接続確認を試す回数と間隔(秒)。MySQL の初回の起動は時間がかかるため。
     */
    private const CONNECT_ATTEMPTS = 10;

    private const CONNECT_INTERVAL_SECONDS = 3;

    public function __construct(private InstallationState $state) {}

    public function run(int $connectAttempts = self::CONNECT_ATTEMPTS): void
    {
        $this->checkDatabase($connectAttempts);

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $exception) {
            InstallerLog::error('マイグレーションに失敗しました。', ['error' => $exception->getMessage()]);

            throw new InstallerStepException('migration', __('データベースのマイグレーションに失敗しました。設定を確かめて、再試行してください。'));
        }

        InstallerLog::info('マイグレーションを実行しました。');

        try {
            Artisan::call('db:seed', ['--class' => InstallSeeder::class, '--force' => true]);
        } catch (Throwable $exception) {
            InstallerLog::error('初期データを入れられませんでした。', ['error' => $exception->getMessage()]);

            throw new InstallerStepException('seed', __('初期データを入れられませんでした。再試行してください。'));
        }

        InstallerLog::info('初期データを入れました。');

        // 公開用のリンク(public/storage)は install.sh が作る。ないときだけ作り、作れなければ知らせる
        if (! file_exists(public_path('storage'))) {
            try {
                Artisan::call('storage:link', ['--relative' => true]);
            } catch (Throwable $exception) {
                InstallerLog::error('アップロード画像の公開用のリンクを作れませんでした。', ['error' => $exception->getMessage()]);

                throw new InstallerStepException('storage', __('アップロード画像の公開用のリンク(public/storage)を作れませんでした。./install.sh を動かし直してください。'));
            }
        }

        Artisan::call('optimize:clear');

        $this->state->markCompleted(InstallerStep::Application);
        InstallerLog::info('アプリケーションのセットアップを終えました。');
    }

    private function checkDatabase(int $attempts): void
    {
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                DB::connection()->getPdo();
                InstallerLog::info('データベースに接続できました。', ['attempts' => $attempt]);

                return;
            } catch (Throwable $exception) {
                DB::purge();

                if ($attempt === $attempts) {
                    InstallerLog::error('データベースに接続できませんでした。', ['error' => $exception->getMessage()]);
                } else {
                    sleep(self::CONNECT_INTERVAL_SECONDS);
                }
            }
        }

        throw new InstallerStepException('database', __('データベースへ接続できませんでした。データベースの段の値を確かめてください。以前のインストールの途中で作ったデータベースが残っている場合は、./install.sh --reset-database で作り直してください。'));
    }
}
