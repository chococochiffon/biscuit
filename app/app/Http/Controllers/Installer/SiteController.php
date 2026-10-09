<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Installer\SiteRequest;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use App\Installer\SiteInstaller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * インストーラーのサイトの段(サイト名・説明・言語・タイムゾーン・公開側と管理画面の URL)。
 * 公開側の URL の既定値は install.sh が .env に入れた値(既定のポート)、管理画面の URL の既定値はいま開いている URL。
 */
class SiteController extends Controller
{
    public function show(Request $request, InstallerManager $installer): View
    {
        $setting = SiteSetting::current();
        // まだ決めていなければ、いま開いている URL(リバースプロキシの裏なら、プロキシが伝える http か https)を初期値にする
        $configured = $installer->state()->isCompleted(InstallerStep::Site);

        return view('installer.site', [
            'installer' => $installer,
            'step' => InstallerStep::Site,
            'values' => [
                'site_title' => old('site_title', $setting?->site_title ?? ''),
                'description' => old('description', $setting?->description ?? ''),
                'locale' => old('locale', config('app.locale')),
                'timezone' => old('timezone', config('app.timezone')),
                'front_url' => old('front_url', $setting?->front_url ?? config('app.front_url')),
                'admin_url' => old('admin_url', $configured ? config('app.url') : $request->getSchemeAndHttpHost()),
            ],
        ]);
    }

    public function store(SiteRequest $request, SiteInstaller $site, InstallerManager $installer): RedirectResponse
    {
        $site->configure($request->validated());

        return redirect()->route($installer->currentStep()->routeName());
    }
}
