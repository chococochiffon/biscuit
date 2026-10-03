<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Installer\InstallerFinalizer;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * インストーラーの完了の段。内容を確かめて表示し、「インストールを完了する」でロックを作って完了の画面を返す
 * (完了したあとは /install に入れないため、完了の画面はリダイレクトせずにその場で返す)。
 */
class FinalizeController extends Controller
{
    public function show(InstallerManager $installer, InstallerFinalizer $finalizer): View
    {
        $checks = $finalizer->checks();

        return view('installer.finalize', [
            'installer' => $installer,
            'step' => InstallerStep::Finalize,
            'checks' => $checks,
            'passes' => array_filter($checks, fn (array $check) => ! $check['ok']) === [],
        ]);
    }

    public function store(InstallerManager $installer, InstallerFinalizer $finalizer): View|RedirectResponse
    {
        if (array_filter($finalizer->checks(), fn (array $check) => ! $check['ok']) !== []) {
            return redirect()->route('installer.finalize')->with('error', __('確認できていない項目があります。'));
        }

        $finalizer->finish();

        return view('installer.complete', [
            'frontUrl' => SiteSetting::current()?->front_url ?: config('app.front_url'),
            'adminUrl' => route('admin.login'),
        ]);
    }
}
