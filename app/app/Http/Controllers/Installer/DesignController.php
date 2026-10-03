<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use App\Installer\TemplateInstaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * インストーラーのデザインの段。今はデフォルトテンプレートだけを選べる(ビルダーで作る選び方は次の段階で足す)。
 * デフォルトテンプレートもビルダーの JSON なので、インストールのあとに管理画面のビルダーで直せる。
 */
class DesignController extends Controller
{
    public function show(InstallerManager $installer): View
    {
        return view('installer.design', ['installer' => $installer, 'step' => InstallerStep::Design]);
    }

    public function store(TemplateInstaller $templates, InstallerManager $installer): RedirectResponse
    {
        $templates->installDefault();

        return redirect()->route($installer->currentStep()->routeName());
    }
}
