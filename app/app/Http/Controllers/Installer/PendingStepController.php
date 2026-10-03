<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * まだ作っていない段(データベース以降。次の更新で段ごとのコントローラーに置き換える)。
 */
class PendingStepController extends Controller
{
    public function __invoke(Request $request, InstallerManager $installer): View
    {
        return view('installer.pending', [
            'installer' => $installer,
            'step' => InstallerStep::from((string) $request->route('step')),
        ]);
    }
}
