<?php

namespace App\Console\Commands;

use App\Installer\HealthChecker;
use App\Services\DoctorService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * インストール後の診断(./biscuit doctor が app コンテナの中で動かす)。必須の問題があれば終了コード 1。
 * --json は、ほかのコマンド(これからの ./biscuit update の前の確認)が読むための形。
 */
#[Signature('biscuit:doctor {--json : 結果を JSON で出す} {--offline : 新しい版を GitHub に問い合わせない(キャッシュだけを見る)}')]
#[Description('Biscuit の動作に問題がないかを診断する')]
class DoctorCommand extends Command
{
    public function handle(DoctorService $doctor): int
    {
        $checks = $doctor->check(fetchUpdate: ! $this->option('offline'));
        $passes = HealthChecker::passes($checks);

        if ($this->option('json')) {
            $this->line((string) json_encode(['passes' => $passes, 'checks' => $checks], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $passes ? self::SUCCESS : self::FAILURE;
        }

        foreach ($checks as $check) {
            $mark = match (true) {
                $check['ok'] => '<fg=green>✔</>',
                $check['level'] === 'required' => '<fg=red>✘</>',
                default => '<fg=yellow>!</>',
            };
            $this->line("  {$mark} {$check['label']}".($check['detail'] ? "  <fg=gray>{$check['detail']}</>" : ''));
        }

        return $passes ? self::SUCCESS : self::FAILURE;
    }
}
