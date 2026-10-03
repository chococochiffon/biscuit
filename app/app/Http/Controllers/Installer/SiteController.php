<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Installer\SiteRequest;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use App\Installer\SiteInstaller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * インストーラーのサイトの段(サイト名・説明・言語・タイムゾーン・公開側と管理画面の URL)。
 * URL の既定値は install.sh が .env に入れた値(既定のポート)。
 */
class SiteController extends Controller
{
    public function show(InstallerManager $installer): View
    {
        $setting = SiteSetting::current();

        return view('installer.site', [
            'installer' => $installer,
            'step' => InstallerStep::Site,
            'values' => [
                'site_title' => old('site_title', $setting?->site_title ?? ''),
                'description' => old('description', $setting?->description ?? ''),
                'locale' => old('locale', config('app.locale')),
                'timezone' => old('timezone', config('app.timezone')),
                'front_url' => old('front_url', $setting?->front_url ?? config('app.front_url')),
                'admin_url' => old('admin_url', config('app.url')),
            ],
        ]);
    }

    public function store(SiteRequest $request, SiteInstaller $site, InstallerManager $installer): RedirectResponse
    {
        $site->configure($request->validated());

        return redirect()->route($installer->currentStep()->routeName());
    }
}
