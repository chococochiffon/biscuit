<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContentModelRelationRequest;
use App\Http\Requests\UpdateContentModelRelationRequest;
use App\Models\ContentModelRelation;
use App\Rules\AllowedTableName;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * データ種別紐付けの管理画面。サイト設定画面のデータ種別紐付け管理モーダルが使う JSON は ContentModelRelationJsonController が返す。
 */
class ContentModelRelationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $contentModelRelations = ContentModelRelation::query()
            ->latest('updated_at')
            ->paginate(config('limits.admin_per_page'));

        return view('admin.content_model_relations.index', compact('contentModelRelations'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $tableNames = AllowedTableName::availableTables();

        return view('admin.content_model_relations.create', compact('tableNames'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreContentModelRelationRequest $request): RedirectResponse
    {
        AuditLogger::createWithLog(fn () => ContentModelRelation::create($request->validated()));

        return redirect()->route('admin.content-model-relations.index')->with('status', __('データ種別の紐付けを登録しました。'));
    }

    /**
     * Display the specified resource.
     */
    public function show(ContentModelRelation $contentModelRelation): View
    {
        return view('admin.content_model_relations.show', compact('contentModelRelation'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ContentModelRelation $contentModelRelation): View
    {
        // 登録済みのtable_nameが取得元テーブル一覧から漏れていても選択肢から消えないようにする
        $tableNames = collect(AllowedTableName::availableTables())
            ->push($contentModelRelation->table_name)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return view('admin.content_model_relations.edit', compact('contentModelRelation', 'tableNames'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateContentModelRelationRequest $request, ContentModelRelation $contentModelRelation): RedirectResponse
    {
        AuditLogger::updateWithLog($contentModelRelation, fn () => $contentModelRelation->update($request->validated()));

        return redirect()->route('admin.content-model-relations.index')->with('status', __('データ種別の紐付けを更新しました。'));
    }

    /**
     * Remove the specified resource from storage(call_contents・レイアウトの部品で使用中の場合は削除しない)。
     */
    public function destroy(ContentModelRelation $contentModelRelation): RedirectResponse
    {
        $inUseMessage = $contentModelRelation->inUseMessage();

        if ($inUseMessage !== null) {
            return redirect()->route('admin.content-model-relations.index')->with('error', $inUseMessage);
        }

        AuditLogger::deleteWithLog($contentModelRelation);

        return redirect()->route('admin.content-model-relations.index')->with('status', __('データ種別の紐付けを削除しました。'));
    }
}
