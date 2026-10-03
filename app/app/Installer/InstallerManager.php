<?php

namespace App\Installer;

use App\Models\Administrator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * インストール済みかの判定と、インストーラーの段の進み具合をまとめる。
 *
 * インストール済みかは、次のどれかで判定する(APP_INSTALLED のような設定だけには頼らない)。
 * - ロックファイル(local ディスクの installed)がある
 * - DB に管理者がいる(このインストーラーより前からある環境。見つけたらロックファイルを作る。インストーラーの途中は除く)
 * - config('installer.assume_installed')(テスト用)
 * インストール済みなら /install へは入れず(EnsureNotInstalled)、まだならほかの画面・API はインストーラーへ回す(RedirectIfNotInstalled)。
 */
class InstallerManager
{
    public const LOCK_FILE = 'installed';

    /**
     * 1 回のリクエストの中での判定の結果(DB への問い合わせを繰り返さない)。
     */
    private ?bool $installed = null;

    public function __construct(private InstallationState $state) {}

    public function isInstalled(): bool
    {
        if (config('installer.assume_installed') || Storage::disk('local')->exists(self::LOCK_FILE)) {
            return true;
        }

        return $this->installed ??= $this->detectExistingInstallation();
    }

    /**
     * インストールを終えたことにする(ロックファイルを作り、途中の状態を消す)。
     */
    public function lock(): void
    {
        Storage::disk('local')->put(self::LOCK_FILE, json_encode(['installed_at' => now()->toIso8601String(), 'version' => config('biscuit.version')]));
        $this->state->clear();
        $this->installed = true;
    }

    public function state(): InstallationState
    {
        return $this->state;
    }

    /**
     * まだ終えていない最初の段(すべて終えていれば完了の段)。
     */
    public function currentStep(): InstallerStep
    {
        foreach (InstallerStep::cases() as $step) {
            if (! $this->state->isCompleted($step)) {
                return $step;
            }
        }

        return InstallerStep::Finalize;
    }

    /**
     * その段に入れるか(前の段をすべて終えているか)。
     */
    public function canEnter(InstallerStep $step): bool
    {
        foreach ($step->previous() as $previous) {
            if (! $this->state->isCompleted($previous)) {
                return false;
            }
        }

        return true;
    }

    /**
     * このインストーラーより前からある環境か(DB に管理者がいる)。見つけたらロックファイルを作る。
     * DB につながらない(インストール前)ときは false。
     */
    private function detectExistingInstallation(): bool
    {
        // インストーラーの途中(管理者の段で管理者を作ったあと)は、インストール済みとみなさない
        if ($this->state->hasProgress()) {
            return false;
        }

        try {
            if (! Schema::hasTable('administrators') || ! Administrator::query()->exists()) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        Storage::disk('local')->put(self::LOCK_FILE, json_encode(['installed_at' => now()->toIso8601String(), 'detected' => true]));

        return true;
    }
}
