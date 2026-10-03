<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Installer\HealthChecker;
use App\Installer\InstallerFinalizer;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * インストーラーの完了の段。内容を確かめて(HealthChecker。必須を満たさないと完了できず、推奨は警告だけ)表示し、「インストールを完了する」でロックを作って完了の画面を返す
 * (完了したあとは /install に入れないため、完了の画面はリダイレクトせずにその場で返す)。
 */
class FinalizeController extends Controller
{
    public function show(InstallerManager $installer, HealthChecker $health): View
    {
        $checks = $health->check();

        return view('installer.finalize', [
            'installer' => $installer,
            'step' => InstallerStep::Finalize,
            'required' => array_values(array_filter($checks, fn (array $check) => $check['level'] === 'required')),
            'recommended' => array_values(array_filter($checks, fn (array $check) => $check['level'] === 'recommended')),
            'passes' => HealthChecker::passes($checks),
        ]);
    }

    public function store(InstallerFinalizer $finalizer, HealthChecker $health): View|RedirectResponse
    {
        if (! HealthChecker::passes($health->check())) {
            return redirect()->route('installer.finalize')->with('error', __('確認できていない項目があります。'));
        }

        $finalizer->finish();

        return view('installer.complete', [
            'frontUrl' => SiteSetting::current()?->front_url ?: config('app.front_url'),
            'adminUrl' => route('admin.login'),
        ]);
    }
}
