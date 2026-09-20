<?php

namespace App\View\Composers;

use App\Models\SiteSetting;
use Illuminate\View\View;

class SiteSettingComposer
{
    /**
     * 登録済みのサイト設定(未登録の場合はnull)をビューへ共有する。
     */
    public function compose(View $view): void
    {
        $view->with('currentSiteSetting', SiteSetting::query()->first());
    }
}
