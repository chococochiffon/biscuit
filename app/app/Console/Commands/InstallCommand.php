<?php

namespace App\Console\Commands;

use App\Installer\ApplicationInstaller;
use App\Installer\EnvironmentPreparer;
use App\Installer\HealthChecker;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use App\Installer\InstallerStepException;
use App\Installer\RequirementChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * インストーラーのコマンド。インストールの処理はブラウザのインストーラーと共通のサービス(app/Installer)に置き、ここからも同じものを呼ぶ。
 * install.sh(ホスト側)は、決まった処理だけをこのコマンドで動かす(--prepare・--step=application)。
 */
#[Signature('biscuit:install
    {--status : インストールの状態(終えた段・環境の確認・インストールの確認)を表示する}
    {--prepare : .env に本番向けの初期値を入れる(install.sh が最初に呼ぶ)}
    {--step= : 段の処理を動かす(application)}')]
#[Description('Biscuit のインストールの状態を表示し、install.sh から決まった処理を動かす')]
class InstallCommand extends Command
{
    /**
     * --step で動かせる段。
     *
     * @var list<string>
     */
    private const STEPS = ['application'];

    public function handle(InstallerManager $installer, RequirementChecker $checker): int
    {
        if ($installer->isInstalled()) {
            $this->info('Biscuit はインストール済みです。');

            return self::SUCCESS;
        }

        if ($this->option('prepare')) {
            app(EnvironmentPreparer::class)->prepare();
            $this->info('.env に本番向けの初期値を入れました。');

            return self::SUCCESS;
        }

        if (($step = $this->option('step')) !== null) {
            return $this->runStep((string) $step, $installer);
        }

        $this->table(['段', '状態'], array_map(fn (InstallerStep $step) => [
            $step->label(),
            $installer->state()->isCompleted($step) ? '済み' : ($step === $installer->currentStep() ? '今の段' : '-'),
        ], InstallerStep::cases()));

        $results = $checker->check();
        $this->table(['環境の確認', '結果'], array_map(fn (array $result) => [$result['label'], $result['ok'] ? 'OK' : (string) $result['message']], $results));

        $this->table(['インストールの確認', '種類', '結果', '詳しく'], array_map(fn (array $check) => [
            $check['label'],
            $check['level'] === 'required' ? '必須' : '推奨',
            $check['ok'] ? 'OK' : ($check['level'] === 'required' ? 'NG' : '警告'),
            (string) $check['detail'],
        ], app(HealthChecker::class)->check()));

        return RequirementChecker::passes($results) ? self::SUCCESS : self::FAILURE;
    }

    private function runStep(string $step, InstallerManager $installer): int
    {
        if (! in_array($step, self::STEPS, true)) {
            $this->error("この段はコマンドから動かせません: {$step}");

            return self::INVALID;
        }

        if (! $installer->canEnter(InstallerStep::from($step))) {
            $this->error('前の段を終えていません。ブラウザのインストーラーで進めてください。');

            return self::FAILURE;
        }

        try {
            app(ApplicationInstaller::class)->run();
        } catch (InstallerStepException $exception) {
            // install.sh が 1 行目を失敗した処理の名前として読む
            $this->line("stage={$exception->stage}");
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('アプリケーションのセットアップを終えました。');

        return self::SUCCESS;
    }
}
