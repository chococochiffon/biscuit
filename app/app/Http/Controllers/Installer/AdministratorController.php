<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Installer\AdministratorRequest;
use App\Installer\AdministratorInstaller;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * インストーラーの管理者の段(最初のスーパー管理者を作る)。作り終えていれば、次の段へ回す。
 */
class AdministratorController extends Controller
{
    public function show(InstallerManager $installer): View|RedirectResponse
    {
        if ($installer->state()->isCompleted(InstallerStep::Administrator)) {
            return redirect()->route($installer->currentStep()->routeName());
        }

        return view('installer.administrator', ['installer' => $installer, 'step' => InstallerStep::Administrator]);
    }

    public function store(AdministratorRequest $request, AdministratorInstaller $administrators, InstallerManager $installer): RedirectResponse
    {
        $administrators->create($request->validated('name'), $request->validated('email'), $request->validated('password'));

        return redirect()->route($installer->currentStep()->routeName());
    }
}
