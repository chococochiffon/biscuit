<?php

namespace App\Http\Controllers;

use App\Enums\BuilderComponentKind;
use App\Http\Requests\StorePageBuilderComponentRequest;
use App\Http\Requests\UpdatePageBuilderComponentRequest;
use App\Models\PageBuilder;
use App\Models\PageBuilderComponent;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * コンポーネント(グローバル・独自)の管理画面(一覧・登録・名前の変更・削除)。内容はページビルダーのエディタ(admin.builder.components)で編集する。
 * 種類は登録するときに選び、あとから変えない。ページ・ほかのコンポーネントで使っているコンポーネントは削除できない(使っているところから外してから削除する)。
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
            'usages' => $components->getCollection()->mapWithKeys(fn (PageBuilderComponent $component) => [$component->id => $component->usedBy()->count() + $component->usedByComponents()->count()]),
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
            $component = PageBuilderComponent::newEmpty($request->validated('name'), $request->validated('description'), BuilderComponentKind::from($request->validated('kind')));
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

        return redirect()->route('admin.builder-components.index')->with('status', __('コンポーネントを更新しました。'));
    }

    /**
     * 削除(論理削除)する。ページで使っていれば削除しない。
     */
    public function destroy(PageBuilderComponent $pageBuilderComponent): RedirectResponse
    {
        $usedBy = $pageBuilderComponent->usedBy()->toBase()->map(fn (PageBuilder $builder) => $builder->singlePage?->title ?? __('トップページ'))
            ->merge($pageBuilderComponent->usedByComponents()->map(fn (PageBuilderComponent $component) => __('コンポーネント「:name」', ['name' => $component->name])));

        if ($usedBy->isNotEmpty()) {
            $pages = $usedBy->implode('・');

            return redirect()->route('admin.builder-components.index')
                ->with('error', __('「:name」は次の場所で使っているため削除できません: :pages', ['name' => $pageBuilderComponent->name, 'pages' => $pages]));
        }

        AuditLogger::deleteWithLog($pageBuilderComponent);

        return redirect()->route('admin.builder-components.index')->with('status', __('コンポーネントを削除しました。'));
    }
}
