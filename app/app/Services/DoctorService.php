<?php

namespace App\Services;

use App\Installer\HealthChecker;
use App\Installer\InstallerManager;
use Carbon\CarbonImmutable;

/**
 * インストール後の診断(System Doctor)。インストールの確認(HealthChecker)に、インストール済みか・新しい版・エラーのログを足す。
 * ./biscuit doctor(biscuit:doctor)と、これからの更新(./biscuit update)の前の確認で使う。
 * 必須(required)の問題があれば、更新などを止める。推奨(recommended)は警告だけ。
 */
class DoctorService
{
    public function __construct(
        private HealthChecker $health,
        private InstallerManager $installer,
        private UpdateCheckService $updates,
        private SystemStatusService $system,
        private BackupService $backups,
    ) {}

    /**
     * @return list<array{key: string, label: string, level: 'required'|'recommended', ok: bool, detail: string|null}>
     */
    public function check(bool $fetchUpdate = true): array
    {
        $update = $this->safely(fn () => $this->updates->availableUpdate($fetchUpdate));
        $errors = $this->safely(fn () => $this->system->recentErrors());
        $backup = $this->safely(fn () => $this->backups->latest());
        $backupHours = (int) config('biscuit.backup.warning_hours');
        $backupAt = $backup === null ? null : CarbonImmutable::parse($backup['created_at']);

        return [
            ['key' => 'installed', 'label' => __('インストールを終えている'), 'level' => 'required', 'ok' => $this->installer->isInstalled(), 'detail' => 'v'.config('biscuit.version')],
            ...$this->health->check(),
            [
                'key' => 'update',
                'label' => __('最新の版を使っている'),
                'level' => 'recommended',
                'ok' => $update === null,
                'detail' => $update === null ? null : __('v:version が出ています(./biscuit update で更新)', ['version' => $update['version']]),
            ],
            [
                'key' => 'errors',
                'label' => __('直近 :hours 時間にエラーが出ていない', ['hours' => config('biscuit.error_log_hours')]),
                'level' => 'recommended',
                'ok' => ($errors['count'] ?? 0) === 0,
                'detail' => ($errors['count'] ?? 0) > 0 ? __(':count 件(storage/logs を確かめてください)', ['count' => $errors['count']]) : null,
            ],
            [
                'key' => 'backup',
                'label' => __('直近 :hours 時間にバックアップを作っている', ['hours' => $backupHours]),
                'level' => 'recommended',
                'ok' => $backupAt !== null && $backupAt->greaterThan(now()->subHours($backupHours)),
                'detail' => $backupAt === null ? __('まだありません(./biscuit backup で作れます)') : $backupAt->format('Y-m-d H:i'),
            ],
        ];
    }

    private function safely(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable) {
            return null;
        }
    }
}
