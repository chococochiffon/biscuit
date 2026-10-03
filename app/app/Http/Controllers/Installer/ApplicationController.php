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
    public function show(InstallerManager $installer, HostBridge $host): View|RedirectResponse
    {
        if ($installer->state()->isCompleted(InstallerStep::Application)) {
            return redirect()->route($installer->currentStep()->routeName());
        }

        return view('installer.application', [
            'installer' => $installer,
            'step' => InstallerStep::Application,
            'pending' => $host->isPending(),
            'status' => $host->status(),
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
}
