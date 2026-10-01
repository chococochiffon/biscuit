<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePageBuilderComponentRequest;
use App\Http\Requests\UpdatePageBuilderComponentRequest;
use App\Models\PageBuilder;
use App\Models\PageBuilderComponent;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * グローバルコンポーネントの管理画面(一覧・登録・名前の変更・削除)。内容はページビルダーのエディタ(admin.builder.components)で編集する。
 * ページで使っているコンポーネントは削除できない(使っているページを外してから削除する)。
 */
class PageBuilderComponentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $components = PageBuilderComponent::query()->orderBy('name')->orderBy('id')->paginate(config('limits.admin_per_page'));

        return view('admin.builder_components.index', [
            'components' => $components,
            'usages' => $components->getCollection()->mapWithKeys(fn (PageBuilderComponent $component) => [$component->id => $component->usedBy()->count()]),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.builder_components.create');
    }

    /**
     * 空の下書きで登録し、エディタを開く。
     */
    public function store(StorePageBuilderComponentRequest $request): RedirectResponse
    {
        $component = AuditLogger::createWithLog(function () use ($request) {
            $component = PageBuilderComponent::newEmpty($request->validated('name'), $request->validated('description'));
            $component->save();

            return $component;
        });

        return redirect()->route('admin.builder.components', $component);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PageBuilderComponent $pageBuilderComponent): View
    {
        return view('admin.builder_components.edit', ['builderComponent' => $pageBuilderComponent]);
    }

    /**
     * 名前・説明を変更する。
     */
    public function update(UpdatePageBuilderComponentRequest $request, PageBuilderComponent $pageBuilderComponent): RedirectResponse
    {
        AuditLogger::updateWithLog($pageBuilderComponent, fn () => $pageBuilderComponent->update($request->validated()));

        return redirect()->route('admin.builder-components.index')->with('status', __('グローバルコンポーネントを更新しました。'));
    }

    /**
     * 削除(論理削除)する。ページで使っていれば削除しない。
     */
    public function destroy(PageBuilderComponent $pageBuilderComponent): RedirectResponse
    {
        $usedBy = $pageBuilderComponent->usedBy();

        if ($usedBy->isNotEmpty()) {
            $pages = $usedBy->map(fn (PageBuilder $builder) => $builder->singlePage?->title ?? __('トップページ'))->implode('・');

            return redirect()->route('admin.builder-components.index')
                ->with('error', __('「:name」は次のページで使っているため削除できません: :pages', ['name' => $pageBuilderComponent->name, 'pages' => $pages]));
        }

        AuditLogger::deleteWithLog($pageBuilderComponent);

        return redirect()->route('admin.builder-components.index')->with('status', __('グローバルコンポーネントを削除しました。'));
    }
}
