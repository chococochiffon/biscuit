<?php

namespace App\Services;

use App\Installer\HealthChecker;
use App\Installer\InstallerManager;
use Carbon\CarbonImmutable;

/**
 * インストール後の診断(System Doctor)。インストールの確認(HealthChecker)に、インストール済みか・新しい版・エラーのログを足す。
 * ./biscuit doctor(biscuit:doctor)と、更新(./biscuit update)の前の確認で使う。
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
        $memory = $this->safely(fn () => $this->memory());
        $memoryMinimum = (int) config('biscuit.memory.minimum_mb');
        $memoryEnough = $memory === null || $memoryMinimum <= $memory['memory'] + $memory['swap'];

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
            [
                'key' => 'memory',
                'label' => __('更新でイメージを作れるメモリがある'),
                'level' => 'recommended',
                'ok' => $memoryEnough,
                'detail' => match (true) {
                    $memory === null => null,
                    $memoryEnough => __('メモリ :memoryMB・スワップ :swapMB', $memory),
                    default => __('メモリ :memoryMB・スワップ :swapMB(合計 :minimumMB 以上が要ります。スワップを足してください)', [...$memory, 'minimum' => $memoryMinimum]),
                },
            ],
        ];
    }

    /**
     * サーバー(Docker Desktop では仮想マシン)のメモリとスワップの大きさ(MB)。読めないときは null。
     *
     * @return array{memory: int, swap: int}|null
     */
    private function memory(): ?array
    {
        $path = (string) config('biscuit.memory.meminfo');
        if (! is_readable($path)) {
            return null;
        }

        preg_match_all('/^(MemTotal|SwapTotal):\s+(\d+) kB$/m', (string) file_get_contents($path), $matches, PREG_SET_ORDER);
        $values = array_column($matches, 2, 1);
        if (! isset($values['MemTotal'])) {
            return null;
        }

        return ['memory' => intdiv((int) $values['MemTotal'], 1024), 'swap' => intdiv((int) ($values['SwapTotal'] ?? 0), 1024)];
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
