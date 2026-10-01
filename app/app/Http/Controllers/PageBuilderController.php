<?php

namespace App\Http\Controllers;

use App\Models\PageBuilderComponent;
use App\Models\SinglePage;
use App\Models\SiteSetting;
use Illuminate\View\View;

/**
 * ページビルダーのエディタの画面。対象はトップ(admin/builder/top)・固定ページ(admin/builder/single-pages/{singlePage})と、
 * グローバルコンポーネント(admin/builder/components/{pageBuilderComponent})。
 * 画面は Vue のエディタ(resources/js/builder.ts)を読み込むだけで、内容の取得・保存は PageBuilderJsonController の JSON で行う。
 */
class PageBuilderController extends Controller
{
    /**
     * グローバルコンポーネントのエディタの画面を表示する(ページと同じエディタ。プレビューはない)。
     */
    public function editComponent(PageBuilderComponent $pageBuilderComponent): View
    {
        return view('admin.builder.edit', [
            'title' => $pageBuilderComponent->name,
            'config' => [
                'backUrl' => route('admin.builder-components.index'),
                'endpoints' => [
                    'show' => route('admin.json.builder.components.show', $pageBuilderComponent),
                    'update' => route('admin.json.builder.components.update', $pageBuilderComponent),
                    'publish' => route('admin.json.builder.components.publish', $pageBuilderComponent),
                    'discard' => route('admin.json.builder.components.discard', $pageBuilderComponent),
                    'previewUrl' => null,
                    ...$this->sharedEndpoints(),
                ],
            ],
        ]);
    }

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
                    ...$this->sharedEndpoints(),
                ],
            ],
        ]);
    }

    /**
     * ページとグローバルコンポーネントのエディタで共通の JSON の URL。
     *
     * @return array<string, string>
     */
    private function sharedEndpoints(): array
    {
        return [
            'images' => route('admin.json.builder.images'),
            'templates' => route('admin.json.builder-templates.index'),
            'articleList' => route('admin.json.builder.article-list'),
            'navigation' => route('admin.json.builder.navigation'),
            'gallery' => route('admin.json.builder.gallery'),
            'components' => route('admin.json.builder-components.index'),
        ];
    }
}
