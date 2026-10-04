<?php

namespace App\Console\Commands;

use App\Installer\InstallerManager;
use App\Models\Administrator;
use App\Models\Article;
use App\Models\SinglePage;
use App\Services\BackupService;
use App\Services\UpdateCheckService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Biscuit の状態(版・環境・データベース・件数・新しい版)を表示する(./biscuit status が app コンテナの中で動かす)。
 */
#[Signature('biscuit:status {--json : 結果を JSON で出す}')]
#[Description('Biscuit の版・環境・データベースの状態を表示する')]
class StatusCommand extends Command
{
    public function handle(InstallerManager $installer, UpdateCheckService $updates, BackupService $backups): int
    {
        $status = [
            'version' => (string) config('biscuit.version'),
            'installed' => $installer->isInstalled(),
            'environment' => app()->environment(),
            'php' => PHP_VERSION,
            'laravel' => Application::VERSION,
            'database' => $this->databaseStatus(),
            'latest_backup' => $this->safely(fn () => $backups->latest()['created_at'] ?? null),
            'latest_release' => $this->safely(fn () => $updates->availableUpdate(fetch: false)['version'] ?? null),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $database = $status['database'];
        $this->table(['項目', '値'], [
            ['Biscuit', 'v'.$status['version'].($status['latest_release'] ? "(v{$status['latest_release']} が出ています)" : '')],
            ['インストール', $status['installed'] ? '済み' : 'まだ'],
            ['環境', $status['environment']],
            ['PHP / Laravel', $status['php'].' / '.$status['laravel']],
            ['データベース', $database['connected'] ? '接続できる('.$database['driver'].')' : '接続できない'],
            ['未実行のマイグレーション', $database['connected'] ? (string) $database['pending_migrations'] : '-'],
            ['最新のバックアップ', $status['latest_backup'] ? str_replace('T', ' ', substr($status['latest_backup'], 0, 16)) : 'まだありません'],
            ['管理者 / 記事 / 固定ページ', $database['connected'] ? "{$database['administrators']} / {$database['articles']} / {$database['single_pages']}" : '-'],
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array{connected: bool, driver: string|null, pending_migrations: int|null, administrators: int|null, articles: int|null, single_pages: int|null}
     */
    private function databaseStatus(): array
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            return ['connected' => false, 'driver' => null, 'pending_migrations' => null, 'administrators' => null, 'articles' => null, 'single_pages' => null];
        }

        /** @var Migrator $migrator */
        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));

        return [
            'connected' => true,
            'driver' => DB::connection()->getDriverName(),
            'pending_migrations' => $this->safely(fn () => count(array_diff(array_keys($files), $migrator->getRepository()->getRan()))),
            'administrators' => $this->safely(fn () => Administrator::query()->count()),
            'articles' => $this->safely(fn () => Article::query()->count()),
            'single_pages' => $this->safely(fn () => SinglePage::query()->count()),
        ];
    }

    private function safely(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return null;
        }
    }
}
