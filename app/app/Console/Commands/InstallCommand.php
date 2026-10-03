<?php

namespace App\Console\Commands;

use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use App\Installer\RequirementChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * インストーラーのコマンド。インストールの処理はブラウザのインストーラーと共通のサービス(app/Installer)に置き、ここからも同じものを呼ぶ。
 * install.sh(ホスト側)は、決まった段の処理だけをこのコマンドで動かす。
 */
#[Signature('biscuit:install {--status : インストールの状態(終えた段・環境の確認)を表示する}')]
#[Description('Biscuit のインストールの状態を表示する')]
class InstallCommand extends Command
{
    public function handle(InstallerManager $installer, RequirementChecker $checker): int
    {
        if ($installer->isInstalled()) {
            $this->info('Biscuit はインストール済みです。');

            return self::SUCCESS;
        }

        $this->table(['段', '状態'], array_map(fn (InstallerStep $step) => [
            $step->label(),
            $installer->state()->isCompleted($step) ? '済み' : ($step === $installer->currentStep() ? '今の段' : '-'),
        ], InstallerStep::cases()));

        $results = $checker->check();
        $this->table(['環境の確認', '結果'], array_map(fn (array $result) => [$result['label'], $result['ok'] ? 'OK' : (string) $result['message']], $results));

        return RequirementChecker::passes($results) ? self::SUCCESS : self::FAILURE;
    }
}
