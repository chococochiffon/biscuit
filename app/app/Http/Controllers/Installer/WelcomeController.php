<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Installer\InstallerLog;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use App\Installer\RequirementChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * インストーラーの最初の段(Welcome と環境の確認)。満たしていない項目があれば次へ進ませない。
 * 前回の続き(install.sh を止めて動かし直したときなど)なら、続きの段へ進むボタンも出す。
 */
class WelcomeController extends Controller
{
    public function show(InstallerManager $installer, RequirementChecker $checker): View
    {
        $results = $checker->check();

        return view('installer.requirements', [
            'results' => $results,
            'passes' => RequirementChecker::passes($results),
            'installer' => $installer,
            'step' => InstallerStep::Requirements,
            // 前回の続き(環境の確認より先の段を終えている)なら、続きの段へ進める
            'resumeStep' => $installer->state()->isCompleted(InstallerStep::Requirements) ? $installer->currentStep() : null,
        ]);
    }

    /**
     * 環境を確かめ直し、満たしていれば次の段へ進む。
     */
    public function start(InstallerManager $installer, RequirementChecker $checker): RedirectResponse
    {
        $results = $checker->check();

        if (! RequirementChecker::passes($results)) {
            InstallerLog::error('環境の確認を満たしていません。', ['failed' => array_column(array_filter($results, fn (array $result) => ! $result['ok']), 'key')]);

            return redirect()->route('installer.requirements')->with('error', __('満たしていない項目があります。直してから、もう一度お試しください。'));
        }

        $installer->state()->markCompleted(InstallerStep::Requirements);
        InstallerLog::info('インストールを始めました。', ['version' => config('biscuit.version')]);

        return redirect()->route($installer->currentStep()->routeName());
    }
}
