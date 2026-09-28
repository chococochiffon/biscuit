<?php

namespace App\View\Composers;

use App\Models\CustomPageType;
use Illuminate\View\View;

class CustomPageTypeComposer
{
    /**
     * サイドメニューの「カスタムページ管理」の下に並べるカスタムページの種類(並び順)をビューへ共有する。
     */
    public function compose(View $view): void
    {
        $view->with('sidebarCustomPageTypes', CustomPageType::query()->ordered()->get());
    }
}
