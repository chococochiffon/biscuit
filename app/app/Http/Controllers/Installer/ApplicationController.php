<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Installer\HostBridge;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * インストーラーのアプリケーションの段。「セットアップを始める」で install.sh へ合図(start-services)を置き、
 * install.sh がサービスの起動・ヘルスチェック・biscuit:install --step=application(DB の接続確認・マイグレーション)を動かす。
 * 画面は進み具合を数秒ごとに読み込み直して表示し、失敗したら何に失敗したかと「再試行」を出す。
 */
class ApplicationController extends Controller
{
    /**
     * install.sh が進める処理(host-status.json の stage)と、画面に出す名前。並び順が進む順。
     * 失敗したときの stage(マイグレーション・初期データ・storage)は、biscuit:install --step=application の中の処理。
     */
    public const STAGES = ['build', 'start', 'database', 'application', 'front'];

    public const APPLICATION_STAGES = ['migration', 'seed', 'storage'];

    public function show(InstallerManager $installer, HostBridge $host): View
    {
        $status = $host->status();

        return view('installer.application', [
            'installer' => $installer,
            'step' => InstallerStep::Application,
            'pending' => $host->isPending(),
            'status' => $status,
            'stages' => $this->stages($status),
        ]);
    }

    /**
     * install.sh にサービスの起動とセットアップを頼む(再試行も同じ)。
     */
    public function start(InstallerManager $installer, HostBridge $host): RedirectResponse
    {
        if (! $installer->state()->isCompleted(InstallerStep::Application) && ($host->status()['status'] ?? null) !== 'running') {
            $host->request('start-services');
        }

        return redirect()->route('installer.application');
    }

    /**
     * 処理の一覧と、それぞれの状態(done・running・failed・waiting)。
     *
     * @param  array{status?: string, stage?: string|null}|null  $status
     * @return list<array{key: string, label: string, state: string}>
     */
    private function stages(?array $status): array
    {
        $stage = $status['stage'] ?? null;
        $stage = in_array($stage, self::APPLICATION_STAGES, true) ? 'application' : $stage;
        $current = ($status['status'] ?? null) === 'succeeded' ? count(self::STAGES) : array_search($stage, self::STAGES, true);
        $labels = [
            'build' => __('公開側のイメージを作る'),
            'start' => __('サービスを起動する'),
            'database' => __('データベースの起動を待つ'),
            'application' => __('テーブルと初期データを用意する'),
            'front' => __('公開側のサイトを起動する'),
        ];

        return array_map(fn (string $key, int $index) => [
            'key' => $key,
            'label' => $labels[$key],
            'state' => match (true) {
                $current === false => 'waiting',
                $index < $current => 'done',
                $index > $current => 'waiting',
                ($status['status'] ?? null) === 'failed' => 'failed',
                default => 'running',
            },
        ], self::STAGES, array_keys(self::STAGES));
    }
}
