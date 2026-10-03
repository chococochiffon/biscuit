<?php

namespace App\Http\Middleware\Installer;

use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 前の段を終えていない段と、終えたあとに直せない段(InstallerStep::isEditable())には入れない(今の段へ回す)。
 * 使い方: ->middleware('installer.step:database')
 */
class EnsureInstallerStep
{
    public function __construct(private InstallerManager $installer) {}

    public function handle(Request $request, Closure $next, string $step): Response
    {
        $step = InstallerStep::from($step);
        $state = $this->installer->state();

        if (! $this->installer->canEnter($step) || ($state->isCompleted($step) && ! $step->isEditable($state))) {
            return redirect()->route($this->installer->currentStep()->routeName());
        }

        return $next($request);
    }
}
