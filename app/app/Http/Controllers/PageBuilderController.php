<?php

namespace App\Http\Controllers;

use App\Models\SinglePage;
use App\Models\SiteSetting;
use Illuminate\View\View;

/**
 * ページビルダーのエディタの画面。対象はトップ(admin/builder/top)と固定ページ(admin/builder/single-pages/{singlePage})。
 * 画面は Vue のエディタ(resources/js/builder.ts)を読み込むだけで、内容の取得・保存は PageBuilderJsonController の JSON で行う。
 */
class PageBuilderController extends Controller
{
    /**
     * エディタの画面を表示する。
     */
    public function edit(?SinglePage $singlePage = null): View
    {
        $routePrefix = $singlePage === null ? 'admin.json.builder.top.' : 'admin.json.builder.single-pages.';
        $routeParameters = $singlePage === null ? [] : [$singlePage];
        $currentSiteSetting = SiteSetting::current();

        return view('admin.builder.edit', [
            'title' => $singlePage?->title ?? __('トップページ'),
            'config' => [
                'backUrl' => $singlePage === null
                    ? ($currentSiteSetting ? route('admin.site-settings.show', $currentSiteSetting) : route('admin.dashboard'))
                    : route('admin.single-pages.edit', $singlePage),
                'endpoints' => [
                    'show' => route($routePrefix.'show', $routeParameters),
                    'update' => route($routePrefix.'update', $routeParameters),
                    'publish' => route($routePrefix.'publish', $routeParameters),
                    'discard' => route($routePrefix.'discard', $routeParameters),
                    'previewUrl' => route($routePrefix.'preview-url', $routeParameters),
                    'images' => route('admin.json.builder.images'),
                ],
            ],
        ]);
    }
}
